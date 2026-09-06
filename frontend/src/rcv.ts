export type Round = {
  counts: Record<number, number>;
  exhausted: number;
  majority: number;
  eliminated: number | null;
  winner: number | null;
};

export type RcvResult = { winner: number | null; rounds: Round[] };

export function calculateRcv(ballots: number[][], candidateIds: number[]): RcvResult {
  let active = [...new Set(candidateIds.map(Number))].sort((a, b) => a - b);
  const rounds: Round[] = [];

  if (active.length === 0) return { winner: null, rounds };

  while (active.length > 1) {
    const counts: Record<number, number> = Object.fromEntries(active.map(id => [id, 0]));
    let exhausted = 0;

    for (const ballot of ballots) {
      const choice = ballot.find(id => active.includes(id));
      if (choice === undefined) exhausted++;
      else counts[choice]++;
    }

    const continuingVotes = Object.values(counts).reduce((a, b) => a + b, 0);
    const majority = Math.floor(continuingVotes / 2) + 1;

    for (const [candidate, count] of Object.entries(counts)) {
      if (continuingVotes > 0 && count >= majority) {
        const winner = Number(candidate);
        rounds.push({ counts, exhausted, majority, eliminated: null, winner });
        return { winner, rounds };
      }
    }

    const minimum = Math.min(...Object.values(counts));
    const eliminated = Object.entries(counts)
      .filter(([, count]) => count === minimum)
      .map(([candidate]) => Number(candidate))
      .sort((a, b) => a - b)[0];

    rounds.push({ counts, exhausted, majority, eliminated, winner: null });
    active = active.filter(id => id !== eliminated);
  }

  return { winner: active[0] ?? null, rounds };
}
