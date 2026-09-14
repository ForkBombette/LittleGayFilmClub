import test from 'node:test';
import assert from 'node:assert/strict';
import { applyMetadata } from '../dist/metadata-search.js';

test('import preserves pitch, mystery, actor and CSRF while replacing descriptive fields', () => {
 const fields=Object.fromEntries(['title','year','summary','image_url','pitch','alias','mystery','csrf','userId'].map(name=>[name,{value:`original ${name}`} ]));
 const form={elements:{namedItem:name=>fields[name]}};
 applyMetadata(form,{id:9,title:'Selected film',year:'1980',summary:'Synopsis',image_url:'https://image.tmdb.org/t/p/w500/test.jpg'});
 assert.equal(fields.title.value,'Selected film');assert.equal(fields.year.value,'1980');
 for(const name of ['pitch','alias','mystery','csrf','userId']) assert.equal(fields[name].value,`original ${name}`);
});
test('missing imported metadata clears previous film details',()=>{
 const fields=Object.fromEntries(['title','year','summary','image_url'].map(name=>[name,{value:'Previous film'}]));
 applyMetadata({elements:{namedItem:name=>fields[name]}},{id:10,title:'New film',year:'',summary:'',image_url:''});
 assert.deepEqual(Object.values(fields).map(field=>field.value),['New film','','','']);
});
test('watched import preserves the date, election link and recorder fields', () => {
 const fields=Object.fromEntries(['title','year','summary','image_url','watched_on','election_id','action','csrf','userId'].map(name=>[name,{value:`original ${name}`} ]));
 applyMetadata({elements:{namedItem:name=>fields[name]}},{id:10,title:'Past film',year:'1980',summary:'Imported synopsis',image_url:'https://image.tmdb.org/t/p/w500/test.jpg'});
 assert.equal(fields.title.value,'Past film');assert.equal(fields.summary.value,'Imported synopsis');
 for(const name of ['watched_on','election_id','action','csrf','userId']) assert.equal(fields[name].value,`original ${name}`);
});
