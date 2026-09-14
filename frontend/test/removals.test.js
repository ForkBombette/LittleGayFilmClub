import test from 'node:test';
import assert from 'node:assert/strict';
import { reconcileCandidates } from '../dist/removals.js';
import { previewBallots } from '../dist/preview.js';
import { calculateRcv } from '../dist/rcv.js';

test('removal keeps current draft order and original committed baseline', () => {
 const movies=[1,2,3].map(id=>({id,title:`Film ${id}`}));
 const baseline={'1':[1,2,3],'2':[1,3,2]};const before=JSON.stringify(baseline);
 const update=reconcileCandidates(movies,[3,1,2],[2,3]);
 assert.deepEqual(update.ranking,[3,2]);assert.deepEqual(update.removed.map(m=>m.id),[1]);
 const result=calculateRcv(previewBallots(baseline,1,update.ranking),update.movies.map(m=>m.id));
 assert.equal(result.winner,3);assert.equal(JSON.stringify(baseline),before);
 assert.equal(movies.length,3);
});
test('repeated and total removals are safe', () => {
 const movies=[1,2].map(id=>({id,title:`Film ${id}`}));
 const first=reconcileCandidates(movies,[2,1],[2]);
 const second=reconcileCandidates(first.movies,first.ranking,[]);
 assert.deepEqual(second.movies,[]);assert.deepEqual(second.ranking,[]);
 assert.deepEqual(reconcileCandidates(second.movies,second.ranking,[]).removed,[]);
});
