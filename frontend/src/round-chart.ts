import type { RcvResult } from './rcv.js';
import { transfers } from './preview.js';

type Movie = { id: number; title: string };

/** Persistent elements let CSS interpolate bars even during rapid draft changes. */
export function createRoundChart(container: HTMLElement, movies: Movie[]) {
  function add<K extends keyof HTMLElementTagNameMap>(parent: HTMLElement, tag: K, text = '', className = '') {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    parent.appendChild(node);
    return node;
  }
  container.className = 'round-chart';
  add(container, 'h3', 'Follow the vote');
  add(container, 'p', 'Step through the rounds or replay them. Bars update as you rearrange your draft.');
  const controls = add(container, 'div', '', 'round-controls');
  const previous = add(controls, 'button', 'Previous round');
  const next = add(controls, 'button', 'Next round');
  const replay = add(controls, 'button', 'Replay rounds');
  for (const button of [previous, next, replay]) button.type = 'button';
  const heading = add(container, 'h4');
  heading.setAttribute('aria-live', 'polite');
  const summary = add(container, 'p', '', 'chart-summary');
  const rows = movies.map(movie => {
    const row = add(container, 'div', '', 'chart-candidate');
    const label = add(row, 'div', '', 'chart-label');
    add(label, 'strong', movie.title);
    const count = add(label, 'span');
    const track = add(row, 'div', '', 'chart-track');
    track.setAttribute('aria-hidden', 'true');
    const bar = add(track, 'div', '', 'chart-bar');
    const status = add(row, 'small', '', 'chart-status');
    return { id: Number(movie.id), row, count, bar, status };
  });
  const explanation = add(container, 'p', '', 'chart-transfer');
  const name = (id: number) => movies.find(movie => Number(movie.id) === id)?.title ?? `Movie ${id}`;
  let result: RcvResult = { winner: null, rounds: [] };
  let index = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;

  function stop() {
    if (timer !== undefined) clearTimeout(timer);
    timer = undefined;
    replay.textContent = 'Replay rounds';
  }

  function draw() {
    const round = result.rounds[index];
    previous.disabled = !round || index === 0;
    next.disabled = !round || index === result.rounds.length - 1;
    replay.disabled = result.rounds.length < 2;
    heading.textContent = round ? `Round ${index + 1} of ${result.rounds.length}` : 'Result';
    // Same scale across every round, including exhausted votes.
    const first = result.rounds[0];
    const total = first ? Object.values(first.counts).reduce((sum, count) => sum + count, first.exhausted) : 0;
    summary.textContent = round
      ? `${round.majority} needed for a majority · ${round.exhausted} exhausted · bars show votes out of ${total}`
      : result.winner === null ? 'No winner.' : `${name(result.winner)} wins as the only eligible candidate. No round was tallied.`;
    for (const item of rows) {
      const count = round?.counts[item.id];
      const won = round?.winner === item.id || (!round && result.winner === item.id);
      const eliminated = round?.eliminated === item.id;
      item.count.textContent = count === undefined ? '—' : `${count} ${count === 1 ? 'vote' : 'votes'}`;
      item.bar.style.width = `${total ? (count ?? 0) / total * 100 : 0}%`;
      item.row.dataset.state = won ? 'winner' : eliminated ? 'eliminated' : count === undefined ? 'out' : 'active';
      item.status.textContent = won ? 'Winner' : eliminated ? 'Eliminated this round' : count === undefined ? (round ? 'Eliminated earlier' : 'No tally') : 'Continuing';
    }
    if (!round) {
      explanation.textContent = '';
    } else if (round.winner !== null) {
      explanation.textContent = `${name(round.winner)} wins with a majority.`;
    } else if (round.eliminated !== null) {
      const following = result.rounds[index + 1];
      const tied = Object.values(round.counts).filter(count => count === round.counts[round.eliminated!]).length > 1;
      const reason = `${name(round.eliminated)} is eliminated.${tied ? ' Tied lowest; lowest candidate ID breaks the tie.' : ''}`;
      const flows = following ? transfers(round, following) : [];
      explanation.textContent = following
        ? `${reason} To round ${index + 2}: ${flows.length ? flows.map(flow => `${flow.count} → ${flow.id === null ? 'exhausted' : name(flow.id)}`).join(' · ') : 'no votes to transfer'}.`
        : `${reason} ${result.winner === null ? 'No winner.' : `${name(result.winner)} wins as the last remaining candidate; no further round is tallied.`}`;
    } else {
      explanation.textContent = '';
    }
  }

  function advance() {
    timer = setTimeout(() => {
      index++;
      draw();
      if (index < result.rounds.length - 1) advance();
      else stop();
    }, 1800);
  }
  previous.addEventListener('click', () => { stop(); index = Math.max(0, index - 1); draw(); });
  next.addEventListener('click', () => { stop(); index = Math.min(result.rounds.length - 1, index + 1); draw(); });
  replay.addEventListener('click', () => {
    if (timer !== undefined) { stop(); return; }
    index = 0;
    draw();
    replay.textContent = 'Pause replay';
    advance();
  });

  return {
    update(value: RcvResult) {
      stop();
      result = value;
      index = Math.min(index, Math.max(0, result.rounds.length - 1));
      container.hidden = false;
      draw();
    },
    reset() { stop(); index = 0; container.hidden = true; },
  };
}
