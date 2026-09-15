import { createCommentator, draftReaction } from './commentator.js';
import { filmText } from './film-label.js';
import { reconcileCandidates } from './removals.js';
import { setupMovieDetails, type Movie } from './movie-details.js';
import { calculateRcv } from './rcv.js';
import { previewBallots, renderRounds } from './preview.js';
import { createRoundChart } from './round-chart.js';

type Bootstrap = {
  election: { id: number; name: string; status: 'open' | 'closed' } | null;
  viewer: { id: number; display_name: string };
  csrf: string;
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

  const submitButton = document.querySelector<HTMLButtonElement>('#submit-ballot')!;
  const speculative = document.querySelector<HTMLDivElement>('#speculative-result')!;
  const message = document.querySelector<HTMLParagraphElement>('#message')!;
  const chartContainer = document.createElement('div');
  speculative.before(chartContainer);
  let chart = createRoundChart(chartContainer, data.movies);

  const commentator = createCommentator(data.movies);
  commentator.react({ type: 'welcome' });
  let gesture: { ranking: number[]; result: ReturnType<typeof calculateRcv>; movieId: number } | null = null;
  function draftResult() {
    return calculateRcv(previewBallots(data.committedBallots, data.viewer.id, currentRanking()), data.movies.map(movie => Number(movie.id)));
  }
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
    const selectedUser = data.viewer.id;
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

  list.addEventListener('dragstart', event => {
    if (votingClosed) { event.preventDefault(); return; }
    const target = (event.target as HTMLElement).closest<HTMLElement>('li[data-movie-id]');
    if (!target) return;
    gesture = { ranking: currentRanking(), result: draftResult(), movieId: Number(target.dataset.movieId) };
    dragging = target;
    target.classList.add('dragging');
  });

  list.addEventListener('dragend', () => {
    if (gesture && !votingClosed) {
      const reaction = draftReaction(gesture.ranking, currentRanking(), gesture.result, draftResult(), gesture.movieId);
      if (reaction) commentator.react(reaction);
    }
    gesture = null;
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
    const userId = data.viewer.id;

    const submittedRanking = currentRanking();
    submitButton.disabled = true;
    try {
      message.textContent = 'Saving…';
      const response = await fetch('submit_ballot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf },
        body: JSON.stringify({
          electionId: election.id,
          ranking: submittedRanking,
        }),
      });

      const result = await response.json();
      if (!response.ok) {
        message.textContent = result.error ?? 'Ballot save failed.';
        commentator.react({ type: 'failed' });
        if (result.code === 'candidates_changed') {
          gesture = null;
          commentator.react({ type: 'removed' });
          const reconciled = reconcileCandidates(data.movies, currentRanking(), result.candidateIds);
          data.movies = reconciled.movies;
          for (const li of [...list.querySelectorAll<HTMLElement>('li[data-movie-id]')]) {
            if (!reconciled.ranking.includes(Number(li.dataset.movieId))) li.remove();
          }
          dragging?.classList.remove('dragging');
          dragging = null;
          chart.reset();
          chartContainer.replaceChildren();
          chart = createRoundChart(chartContainer, data.movies);
          const parts: Array<string | number> = [result.error + ' Eliminated: '];
          reconciled.removed.forEach((movie, i) => { if (i) parts.push(', '); parts.push(movie.id); });
          parts.push('.'); filmText(message, parts, reconciled.removed);
          renderSpeculative();
          if (!data.movies.length) message.textContent = 'All films have been removed. No ballot was saved.';
        }
        if (result.code === 'election_closed') {
          votingClosed = true;
          gesture = null;
          commentator.react({ type: 'closed' });
          chart.reset();
          speculative.textContent = 'Voting has closed. Reload to view the final result.';
        }
        return;
      }

      commentator.react({ type: 'submitted' });
      data.committedBallots[String(userId)] = submittedRanking;
      message.textContent = `Ballot revision ${result.revisionId} committed. Reload to refresh the authoritative result.`;
      renderSpeculative();
    } catch {
      message.textContent = 'Ballot save failed. Please try again.';
      commentator.react({ type: 'failed' });
    } finally {
      submitButton.disabled = votingClosed || data.movies.length === 0;
    }
  });

  setRanking(data.committedBallots[String(data.viewer.id)] ?? data.movies.map(movie => movie.id));
  renderSpeculative();
  submitButton.disabled = data.movies.length === 0;
  if (!data.movies.length) message.textContent = 'All films have been removed. There is nothing left to rank.';
}

setupVoting();
