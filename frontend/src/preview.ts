import { calculateRcv, type Round } from './rcv.js';

export function previewBallots(snapshot: Record<string, number[]>, userId: number, draft: number[]): number[][] {
  return [...Object.entries(snapshot).filter(([id]) => Number(id) !== userId)
    .map(([, ballot]) => [...ballot]), [...draft]];
}

// With one elimination per round, count deltas are precisely its outgoing transfers.
export function transfers(round: Round, next: Round): Array<{ id: number | null; count: number }> {
  return [...Object.entries(next.counts).map(([id, count]) => ({
    id: Number(id) as number | null, count: count - (round.counts[Number(id)] ?? 0),
  })), { id: null, count: next.exhausted - round.exhausted }].filter(flow => flow.count > 0);
}

export function renderRounds(container: HTMLElement, result: ReturnType<typeof calculateRcv>, movies: Array<{id: number; title: string}>): void {
  const name = (id: number) => movies.find(movie => Number(movie.id) === id)?.title ?? `Movie ${id}`;
  const add = (parent: HTMLElement, tag: string, text: string, className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = className;
    parent.appendChild(element);
    return element;
  };
  container.replaceChildren();
  add(container, 'p', result.winner === null ? 'No winner.' : `With this draft: ${name(result.winner)} wins.`, 'winner');
  result.rounds.forEach((round, index) => {
    const article = add(container, 'article', '', 'round');
    add(article, 'h3', `Round ${index + 1}`);
    const continuing = Object.values(round.counts).reduce((sum, count) => sum + count, 0);
    add(article, 'p', `${continuing} continuing votes · ${round.exhausted} exhausted · ${round.majority} needed for a majority`);
    const table = add(article, 'table', '', 'round-totals');
    const head = add(table, 'thead', '');
    const header = add(head, 'tr', '');
    for (const label of ['Film', 'Votes', 'Status']) add(header, 'th', label).setAttribute('scope', 'col');
    const body = add(table, 'tbody', '');
    for (const [id, count] of Object.entries(round.counts)) {
      const candidate = Number(id);
      const row = add(body, 'tr', '');
      add(row, 'th', name(candidate)).setAttribute('scope', 'row');
      add(row, 'td', String(count));
      add(row, 'td', candidate === round.winner ? 'Winner' : candidate === round.eliminated ? 'Eliminated' : 'Continuing');
    }
    if (round.eliminated !== null) {
      const tied = Object.values(round.counts).filter(count => count === round.counts[round.eliminated!]).length > 1;
      add(article, 'p', `Eliminated: ${name(round.eliminated)}.${tied ? ' Tied lowest: the lowest numeric candidate ID is eliminated.' : ''}`);
      const next = result.rounds[index + 1];
      if (next) {
        const flows = transfers(round, next);
        add(article, 'p', `To round ${index + 2}: ${flows.length ? flows.map(flow => `${flow.count} → ${flow.id === null ? 'exhausted' : name(flow.id)}`).join(' · ') : 'no votes to transfer'}.`, 'transfers');
      } else if (result.winner !== null) {
        add(article, 'p', `${name(result.winner)} wins as the last remaining candidate. The engine does not tally another round.`);
      }
    }
  });
  if (!result.rounds.length && result.winner !== null) add(container, 'p', 'Only one eligible candidate; no elimination rounds are needed.');
}
