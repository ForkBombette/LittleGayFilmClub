import { calculateRcv } from './rcv.js';

type Bootstrap = {
  election: { id: number; name: string };
  users: Array<{ id: number; display_name: string }>;
  movies: Array<{ id: number; title: string; release_year: number | null }>;
  committedBallots: Record<string, number[]>;
};

declare global {
  interface Window { LGFC_BOOTSTRAP: Bootstrap; }
}

const data = window.LGFC_BOOTSTRAP;
const list = document.querySelector<HTMLOListElement>('#ranking-list')!;
const userSelect = document.querySelector<HTMLSelectElement>('#user-select')!;
const submitButton = document.querySelector<HTMLButtonElement>('#submit-ballot')!;
const speculative = document.querySelector<HTMLDivElement>('#speculative-result')!;
const message = document.querySelector<HTMLParagraphElement>('#message')!;

let dragging: HTMLElement | null = null;

function currentRanking(): number[] {
  return [...list.querySelectorAll<HTMLElement>('li[data-movie-id]')]
    .map(li => Number(li.dataset.movieId));
}

function setRanking(ranking: number[]): void {
  const byId = new Map([...list.children].map(node => {
    const el = node as HTMLElement;
    return [Number(el.dataset.movieId), el] as const;
  }));
  for (const id of ranking) {
    const el = byId.get(id);
    if (el) list.appendChild(el);
  }
}

function renderSpeculative(): void {
  const selectedUser = Number(userSelect.value);
  if (!selectedUser) {
    speculative.textContent = 'Choose a voter to start meddling with democracy.';
    return;
  }

  const ballots = Object.entries(data.committedBallots)
    .filter(([userId]) => Number(userId) !== selectedUser)
    .map(([, ballot]) => ballot);
  ballots.push(currentRanking());

  const candidates = data.movies.map(movie => movie.id);
  const result = calculateRcv(ballots, candidates);
  const winner = data.movies.find(movie => movie.id === result.winner);

  speculative.innerHTML = '';
  const p = document.createElement('p');
  p.textContent = winner ? `With this draft: ${winner.title} wins.` : 'No winner.';
  speculative.appendChild(p);

  result.rounds.forEach((round, index) => {
    const line = document.createElement('div');
    const counts = Object.entries(round.counts)
      .map(([id, count]) => `${data.movies.find(m => m.id === Number(id))?.title ?? id}: ${count}`)
      .join(' · ');
    line.textContent = `Round ${index + 1}: ${counts}${round.eliminated ? ` — eliminate ${data.movies.find(m => m.id === round.eliminated)?.title}` : ''}`;
    speculative.appendChild(line);
  });
}

userSelect.addEventListener('change', () => {
  const selectedUser = Number(userSelect.value);
  const committed = data.committedBallots[String(selectedUser)];
  if (committed) setRanking(committed);
  renderSpeculative();
});

list.addEventListener('dragstart', event => {
  const target = (event.target as HTMLElement).closest<HTMLElement>('li[data-movie-id]');
  if (!target) return;
  dragging = target;
  target.classList.add('dragging');
});

list.addEventListener('dragend', () => {
  dragging?.classList.remove('dragging');
  dragging = null;
  renderSpeculative();
});

list.addEventListener('dragover', event => {
  event.preventDefault();
  if (!dragging) return;
  const siblings = [...list.querySelectorAll<HTMLElement>('li:not(.dragging)')];
  const next = siblings.find(sibling => {
    const box = sibling.getBoundingClientRect();
    return event.clientY <= box.top + box.height / 2;
  });
  list.insertBefore(dragging, next ?? null);
});

submitButton.addEventListener('click', async () => {
  const userId = Number(userSelect.value);
  if (!userId) {
    message.textContent = 'Choose a voter first.';
    return;
  }

  message.textContent = 'Saving…';
  const response = await fetch('submit_ballot.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      userId,
      electionId: data.election.id,
      ranking: currentRanking(),
    }),
  });

  const result = await response.json();
  if (!response.ok) {
    message.textContent = result.error ?? 'Ballot save failed.';
    return;
  }

  data.committedBallots[String(userId)] = currentRanking();
  message.textContent = `Ballot revision ${result.revisionId} committed. Reload to refresh the authoritative result.`;
  renderSpeculative();
});

renderSpeculative();
