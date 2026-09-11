import { setupMovieDetails, type Movie } from './movie-details.js';
import { calculateRcv } from './rcv.js';
import { previewBallots, renderRounds } from './preview.js';
import { createRoundChart } from './round-chart.js';

type Bootstrap = {
  election: { id: number; name: string; status: 'open' | 'closed' } | null;
  users: Array<{ id: number; display_name: string }>;
  movies: Movie[];
  browseMovies: Movie[];
  committedBallots: Record<string, number[]>;
};

declare global {
  interface Window { LGFC_BOOTSTRAP: Bootstrap; }
}

const data = window.LGFC_BOOTSTRAP;
setupMovieDetails([...data.movies, ...data.browseMovies]);
function setupVoting(): void {
  const election = data.election;
  if (!election || election.status !== 'open') return;
  const list = document.querySelector<HTMLOListElement>('#ranking-list')!;
  const userSelect = document.querySelector<HTMLSelectElement>('#user-select')!;
  const submitButton = document.querySelector<HTMLButtonElement>('#submit-ballot')!;
  const speculative = document.querySelector<HTMLDivElement>('#speculative-result')!;
  const message = document.querySelector<HTMLParagraphElement>('#message')!;
  const chartContainer = document.createElement('div');
  speculative.before(chartContainer);
  const chart = createRoundChart(chartContainer, data.movies);

  let dragging: HTMLElement | null = null;
  let votingClosed = false;

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
    if (votingClosed) return;
    const selectedUser = Number(userSelect.value);
    if (!selectedUser) {
      chart.reset();
      speculative.textContent = 'Choose a voter to start meddling with democracy.';
      return;
    }

    const ballots = previewBallots(data.committedBallots, selectedUser, currentRanking());
    const result = calculateRcv(ballots, data.movies.map(movie => Number(movie.id)));
    chart.update(result);
    renderRounds(speculative, result, data.movies);
  }

  userSelect.addEventListener('change', () => {
    chart.reset();
    const selectedUser = Number(userSelect.value);
    const committed = data.committedBallots[String(selectedUser)];
    setRanking(committed ?? data.movies.map(movie => Number(movie.id)));
    renderSpeculative();
  });

  list.addEventListener('dragstart', event => {
    if (votingClosed) { event.preventDefault(); return; }
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
    if (!dragging || votingClosed) return;
    const siblings = [...list.querySelectorAll<HTMLElement>('li:not(.dragging)')];
    const next = siblings.find(sibling => {
      const box = sibling.getBoundingClientRect();
      return event.clientY <= box.top + box.height / 2;
    });
    if (dragging.nextElementSibling !== (next ?? null)) {
      list.insertBefore(dragging, next ?? null);
      renderSpeculative();
    }
  });

  submitButton.addEventListener('click', async () => {
    if (votingClosed) return;
    const userId = Number(userSelect.value);
    if (!userId) {
      message.textContent = 'Choose a voter first.';
      return;
    }

    const submittedRanking = currentRanking();
    submitButton.disabled = true;
    try {
      message.textContent = 'Saving…';
      const response = await fetch('submit_ballot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId,
          electionId: election.id,
          ranking: submittedRanking,
        }),
      });

      const result = await response.json();
      if (!response.ok) {
        message.textContent = result.error ?? 'Ballot save failed.';
        if (result.code === 'election_closed') {
          votingClosed = true;
          userSelect.disabled = true;
          chart.reset();
          speculative.textContent = 'Voting has closed. Reload to view the final result.';
        }
        return;
      }

      data.committedBallots[String(userId)] = submittedRanking;
      message.textContent = `Ballot revision ${result.revisionId} committed. Reload to refresh the authoritative result.`;
      renderSpeculative();
    } catch {
      message.textContent = 'Ballot save failed. Please try again.';
    } finally {
      submitButton.disabled = votingClosed;
    }
  });

  renderSpeculative();
}

setupVoting();
