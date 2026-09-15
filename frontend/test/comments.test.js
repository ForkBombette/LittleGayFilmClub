import { test } from 'node:test';
import assert from 'node:assert/strict';
import { setupComments } from '../dist/movie-comments.js';
class Node {
  children=[]; handlers={}; value=''; textContent=''; disabled=false; hidden=false;
  addEventListener(name,fn){this.handlers[name]=fn;}
  append(...nodes){this.children.push(...nodes);}
  replaceChildren(){this.children=[];this.textContent='';}
  trigger(name){this.handlers[name]?.({preventDefault(){}});}
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function harness(){
  const nodes={};
  globalThis.document={querySelector:selector=>nodes[selector]??=new Node(),createElement:()=>new Node()};
  const requests=[];globalThis.fetch=(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve,reject}));
  const shown=[];const open=setupComments(movie=>shown.push(movie.id));
  return {nodes,requests,shown,open};
}
const data=(id,body='',version='')=>({movie:{id},viewerId:1,csrf:'test-csrf',comments:version?[{user_id:1,display_name:'Sophie',body,version,updated_at:'2026-01-01 00:00:00'}]:[]});
const answer=(request,payload,ok=true)=>request.resolve({ok,json:async()=>payload});
test('switching films ignores late detail responses',async()=>{
  const h=harness();h.open(1);h.open(2);
  answer(h.requests[1],data(2,'Second film','v2'));await tick();
  answer(h.requests[0],data(1,'First film','v1'));await tick();
  assert.deepEqual(h.shown,[2]);assert.equal(h.nodes['#comment-body'].value,'Second film');
});
test('failed edits keep drafts and refresh allows deliberate review before retry',async()=>{
  const h=harness();h.open(1);answer(h.requests[0],data(1,'Original','v1'));await tick();
  const field=h.nodes['#comment-body'];field.value='<script>Just text</script>';field.trigger('input');
  h.nodes['#comment-form'].trigger('submit');
  assert.equal(field.disabled,true);assert.equal(JSON.parse(h.requests[1].options.body).version,'v1');
  answer(h.requests[1],{error:'Changed elsewhere; review first.'},false);await tick();
  assert.equal(field.value,'<script>Just text</script>');assert.equal(field.disabled,false);
  h.nodes['#refresh-comments'].trigger('click');answer(h.requests[2],data(1,'Newer comment','v2'));await tick();
  assert.equal(field.value,'<script>Just text</script>');
  h.nodes['#comment-form'].trigger('submit');assert.equal(JSON.parse(h.requests[3].options.body).version,'v2');
  answer(h.requests[3],data(1,field.value,'v3'));await tick();
  assert.equal(h.nodes['#movie-comments'].children[0].children[1].textContent,'<script>Just text</script>');
  assert.equal(h.nodes['#comment-message'].textContent,'Your comment is saved.');
});
test('drafts survive switching films and keep their original conflict token; own deletion clears editor',async()=>{
  const h=harness();h.open(1);answer(h.requests[0],data(1,'Original','v1'));await tick();
  h.nodes['#comment-body'].value='Draft';h.nodes['#comment-body'].trigger('input');
  h.open(2);answer(h.requests[1],data(2));await tick();
  h.open(1);answer(h.requests[2],data(1,'Changed elsewhere','v2'));await tick();
  assert.equal(h.nodes['#comment-body'].value,'Draft');
  h.nodes['#comment-form'].trigger('submit');assert.equal(JSON.parse(h.requests[3].options.body).version,'v1');
  answer(h.requests[3],{error:'Conflict'},false);await tick();
  h.nodes['#refresh-comments'].trigger('click');answer(h.requests[4],data(1,'Changed elsewhere','v2'));await tick();
  h.nodes['#delete-comment'].trigger('click');assert.equal(JSON.parse(h.requests[5].options.body).version,'v2');
  answer(h.requests[5],data(1));await tick();
  assert.equal(h.nodes['#comment-body'].value,'');assert.equal(h.nodes['#delete-comment'].hidden,true);
});
