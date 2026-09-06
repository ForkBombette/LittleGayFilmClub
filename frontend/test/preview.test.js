import { test } from 'node:test';
import { createRoundChart } from '../dist/round-chart.js';
import assert from 'node:assert/strict';
import { calculateRcv } from '../dist/rcv.js';
import { previewBallots, transfers, renderRounds } from '../dist/preview.js';

test('draft replaces saved ballot, adds new voters and does not mutate baseline', () => {
  const snapshot = { 1: [1, 2], 2: [2, 1] };
  const before = structuredClone(snapshot);
  const ballots = previewBallots(snapshot, 1, [2, 1]);
  assert.deepEqual(ballots, [[2, 1], [2, 1]]);
  ballots[0].reverse();
  assert.deepEqual(snapshot, before);
  assert.equal(previewBallots(snapshot, 3, [1, 2]).length, 3);
  assert.equal(calculateRcv(previewBallots(snapshot, 1, [2, 1]), [1, 2]).winner, 2);
});

test('transfers conserve eliminated votes, including exhaustion', () => {
  const result = calculateRcv([[1, 2], [1], [2], [2], [2], [3], [3], [3]], [1, 2, 3]);
  assert.equal(result.rounds[0].eliminated, 1);
  assert.deepEqual(transfers(result.rounds[0], result.rounds[1]), [{id: 2, count: 1}, {id: null, count: 1}]);
  for (let i = 0; i < result.rounds.length - 1; i++) {
    const round = result.rounds[i];
    assert.equal(transfers(round, result.rounds[i+1]).reduce((sum, flow) => sum + flow.count, 0), round.counts[round.eliminated]);
  }
});

// Minimal DOM contract: verifies rendered data/text without a browser dependency.
class Element {
  children = []; textContent = ''; className = ''; style = {}; dataset = {}; handlers = {};
  addEventListener(event, handler) { this.handlers[event] = handler; }
  click() { if (!this.disabled) this.handlers.click?.(); }
  appendChild(child) { this.children.push(child); }
  replaceChildren() { this.children = []; this.textContent = ''; }
  setAttribute() {}
  get text() { return [this.textContent, ...this.children.map(child => child.text)].join(' '); }
}
globalThis.document = { createElement: () => new Element() };
const movies = [{id: 1, title: '<Film & one>'}, {id: 2, title: 'Two'}, {id: 3, title: 'Three'}];

test('round display includes counts, transfers, threshold, exhaustion and winner', () => {
  const root = new Element();
  renderRounds(root, calculateRcv([[1,2],[1],[2],[2],[2],[3],[3],[3]], [1,2,3]), movies);
  for (const text of ['Round 1', '<Film & one>', 'Eliminated', '1 → Two', '1 → exhausted', 'needed for a majority', 'With this draft: Two wins.']) assert.ok(root.text.includes(text), text);
  renderRounds(root, calculateRcv([[2]], [1,2]), movies);
  assert.ok(!root.text.includes('Round 2'));
});

test('empty, single candidate and tied final survivor preserve engine outcomes', () => {
  const root = new Element();
  renderRounds(root, calculateRcv([], []), movies);
  assert.equal(root.text.trim(), 'No winner.');
  renderRounds(root, calculateRcv([], [1]), movies);
  assert.ok(root.text.includes('Only one eligible candidate'));
  renderRounds(root, calculateRcv([[1],[2]], [1,2]), movies);
  assert.ok(root.text.includes('lowest numeric candidate ID'));
  assert.ok(root.text.includes('last remaining candidate'));
  assert.ok(!root.text.includes('Round 2'));
});


test('chart preserves bars, steps through transfers and cancels stale replay on draft update', (t) => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const root = new Element();
  const chart = createRoundChart(root, movies);
  const result = calculateRcv([[1,2],[1],[2],[2],[2],[3],[3],[3]], [1,2,3]);
  chart.update(result);
  const controls = root.children.find(node => node.className === 'round-controls');
  const [previous, next, replay] = controls.children;
  const rows = root.children.filter(node => node.className === 'chart-candidate');
  const bar = rows[1].children[1].children[0];
  assert.equal(bar.style.width, '37.5%');
  assert.ok(previous.disabled);
  next.click();
  assert.ok(root.text.includes('Round 2 of 2'));
  assert.equal(bar.style.width, '50%');
  assert.equal(rows[0].dataset.state, 'out');
  assert.equal(rows[1].dataset.state, 'winner');
  replay.click();
  assert.equal(replay.textContent, 'Pause replay');
  assert.equal(bar.style.width, '37.5%');
  t.mock.timers.tick(1800);
  assert.equal(bar.style.width, '50%');
  assert.equal(replay.textContent, 'Replay rounds');
  replay.click();
  chart.update(calculateRcv([[1]], [1,2,3]));
  t.mock.timers.tick(10000);
  assert.ok(root.text.includes('Round 1 of 1'));
  assert.ok(replay.disabled);
  assert.equal(rows[1].children[1].children[0], bar);
  chart.reset();
  assert.equal(root.hidden, true);
  chart.update(calculateRcv([], [1]));
  assert.ok(root.text.includes('only eligible candidate'));
  assert.equal(root.hidden, false);
});
