import fs from 'node:fs';
import { calculateRcv } from '../dist/rcv.js';

const cases = JSON.parse(fs.readFileSync(new URL('../../tests/fixtures/rcv_cases.json', import.meta.url), 'utf8'));
let failures = 0;

for (const testCase of cases) {
  const result = calculateRcv(testCase.ballots, testCase.candidates);
  const ok = result.winner === testCase.winner;
  console.log(`${ok ? 'PASS' : 'FAIL'} - ${testCase.name}`);
  if (!ok) {
    console.log(`  expected ${testCase.winner}, got ${result.winner}`);
    failures++;
  }
}

process.exitCode = failures === 0 ? 0 : 1;
