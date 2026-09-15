import { filmText, type FilmLabel } from './film-label.js';
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

export function renderRounds(container: HTMLElement, result: ReturnType<typeof calculateRcv>, movies: FilmLabel[]): void {
  const add = (parent: HTMLElement, tag: string, text: string, className = '') => {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = className;
    parent.appendChild(element);
    return element;
  };
  container.replaceChildren();
  filmText(add(container, 'p', '', 'winner'), result.winner === null ? ['No winner.'] : ['With this draft: ', result.winner, ' wins.'], movies);
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
      const label = add(row, 'th', ''); label.setAttribute('scope', 'row'); filmText(label, [candidate], movies);
      add(row, 'td', String(count));
      add(row, 'td', candidate === round.winner ? 'Winner' : candidate === round.eliminated ? 'Eliminated' : 'Continuing');
    }
    if (round.eliminated !== null) {
      const tied = Object.values(round.counts).filter(count => count === round.counts[round.eliminated!]).length > 1;
      filmText(add(article, 'p', ''), ['Eliminated: ', round.eliminated, `.${tied ? ' Tied lowest: the lowest numeric candidate ID is eliminated.' : ''}`], movies);
      const next = result.rounds[index + 1];
      if (next) {
        const flows = transfers(round, next);
        const parts: Array<string | number> = [`To round ${index + 2}: `];
        flows.forEach((flow, i) => { if (i) parts.push(' · '); parts.push(`${flow.count} → `, flow.id ?? 'exhausted'); });
        if (!flows.length) parts.push('no votes to transfer');
        parts.push('.'); filmText(add(article, 'p', '', 'transfers'), parts, movies);
      } else if (result.winner !== null) {
        filmText(add(article, 'p', ''), [result.winner, ' wins as the last remaining candidate. The engine does not tally another round.'], movies);
      }
    }
  });
  if (!result.rounds.length && result.winner !== null) add(container, 'p', 'Only one eligible candidate; no elimination rounds are needed.');
}
