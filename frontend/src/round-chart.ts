import type { RcvResult } from './rcv.js';
import { transfers } from './preview.js';

type Movie = { id: number; title: string; release_year?: number | null };

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
  const trendLabel = add(container, 'p', '', 'chart-trend-label');
  const viewport = add(container, 'div', '', 'chart-scroll');
  viewport.tabIndex = 0;
  viewport.setAttribute('role', 'region');
  viewport.setAttribute('aria-label', 'Candidate vote bars; scroll horizontally for more films');
  const plot = add(viewport, 'div', '', 'chart-columns');
  plot.style.gridTemplateColumns = `repeat(${Math.max(1, movies.length)}, minmax(100px, 1fr))`;
  plot.style.minWidth = String(Math.max(1, movies.length) * 100) + 'px';
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 1000 220');
  svg.setAttribute('preserveAspectRatio', 'none');
  svg.setAttribute('class', 'chart-trend');
  svg.setAttribute('aria-hidden', 'true');
  const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
  line.setAttribute('vector-effect', 'non-scaling-stroke');
  svg.appendChild(line);
  plot.appendChild(svg);
  const palette = ['#6950a1', '#287e8b', '#b3563d', '#397547', '#a34878', '#85641b', '#4265a8', '#795a45'];
  const title = (movie: Movie) => movie.title + (movie.release_year ? ` (${movie.release_year})` : '');
  const rows = movies.map(movie => {
    const row = add(plot, 'div', '', 'chart-candidate');
    const count = add(row, 'span', '', 'chart-vote-count');
    const track = add(row, 'div', '', 'chart-track');
    track.setAttribute('aria-hidden', 'true');
    const bar = add(track, 'div', '', 'chart-bar');
    bar.style.backgroundColor = palette[(Number(movie.id) - 1) % palette.length];
    const label = add(row, 'button', title(movie), 'film-title');
    label.type = 'button';
    label.dataset.details = String(movie.id);
    label.setAttribute('aria-haspopup', 'dialog');
    const status = add(row, 'small', '', 'chart-status');
    return { id: Number(movie.id), row, count, bar, status };
  });
  const explanation = add(container, 'p', '', 'chart-transfer');
  const name = (id: number) => { const movie = movies.find(movie => Number(movie.id) === id); return movie ? title(movie) : `Movie ${id}`; };
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
    // Decorative index deliberately independent of ballots, vote totals and RCV rules.
    const trend = movies.map(movie => 20 + ((Number(movie.id) * 37 + index * 19) % 61));
    line.setAttribute('points', trend.map((value, i) => `${(i + .5) * 1000 / movies.length},${220 * (1 - value / 100)}`).join(' '));
    svg.style.display = movies.length > 1 && !!round ? '' : 'none';
    trendLabel.textContent = round && trend.length > 1
      ? `Club trend index: ${Math.round(trend.reduce((sum, value) => sum + value, 0) / trend.length)} · entirely unscientific. Dashed line is not votes.`
      : '';
    for (const item of rows) {
      const count = round?.counts[item.id];
      const won = result.winner === item.id && (!round || index === result.rounds.length - 1);
      const eliminated = round?.eliminated === item.id;
      item.count.textContent = count === undefined ? '—' : `${count} ${count === 1 ? 'vote' : 'votes'}`;
      item.bar.style.height = `${total ? (count ?? 0) / total * 100 : 0}%`;
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
