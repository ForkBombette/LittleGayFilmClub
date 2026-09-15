import { test } from 'node:test';
import assert from 'node:assert/strict';
import { calculateRcv } from '../dist/rcv.js';
import { draftReaction, createCommentator } from '../dist/commentator.js';

const result=(winner, eliminated=[])=>({winner,rounds:eliminated.map(id=>({eliminated:id}))});
test('completed gestures report winner changes and leave election data untouched',()=>{
  const before=[1,2,3], after=[2,1,3];
  const previous=calculateRcv([before],[1,2,3]), current=calculateRcv([after],[1,2,3]);
  const original=JSON.stringify([before,after,previous,current]);
  assert.deepEqual(draftReaction(before,after,previous,current,2),{type:'winner',movieId:2});
  assert.equal(JSON.stringify([before,after,previous,current]),original);
});
test('no-op and reverted gestures are silent; elimination changes take priority over promotion',()=>{
  assert.equal(draftReaction([1,2],[1,2],result(1),result(1),1),null);
  assert.deepEqual(draftReaction([1,2,3],[2,1,3],result(3,[1]),result(3,[2]),2),{type:'elimination'});
});
test('movement direction refers to the dragged film, not a displaced neighbour',()=>{
  assert.deepEqual(draftReaction([1,2,3],[3,1,2],result(1),result(1),3),{type:'promoted',movieId:3});
  assert.deepEqual(draftReaction([1,2,3],[2,3,1],result(1),result(1),1),{type:'demoted',movieId:1});
});
class Node {
  children=[];attrs={};textContent='';classList={add(){}};
  append(...nodes){this.children.push(...nodes)}
  appendChild(node){this.children.push(node)}
  setAttribute(key,value){this.attrs[key]=value}
}
test('commentary uses public labels as plain text, avoids repeats, and makes no network request',()=>{
  globalThis.document={body:new Node(),createElement:()=>new Node()};
  globalThis.fetch=()=>{throw new Error('Commentator must not fetch')};
  const c=createCommentator([{id:1,title:'<img src=x onerror=alert(1)>',release_year:1985},{id:2,title:'Mystery option'}],()=>0);
  c.react({type:'winner',movieId:1});
  const panel=document.body.children[0], bubble=panel.children[2];
  assert.ok(bubble.textContent.includes('<img src=x onerror=alert(1)> (1985)'));
  assert.equal(bubble.children.length,0);
  const first=bubble.textContent;c.react({type:'winner',movieId:1});assert.notEqual(bubble.textContent,first);
  c.react({type:'promoted',movieId:2});assert.ok(bubble.textContent.includes('Mystery option'));
  assert.equal(panel.attrs['aria-live'],'off');
  for(const type of ['welcome','submitted','failed','removed','closed','elimination','unchanged']) {
    c.react({type});assert.ok(bubble.textContent.length>0);
  }
});
