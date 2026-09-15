import type { RcvResult } from './rcv.js';
import { filmLabel, type FilmLabel } from './film-label.js';

export type CommentEvent =
  | { type: 'welcome' | 'submitted' | 'failed' | 'removed' | 'closed' }
  | { type: 'promoted' | 'demoted' | 'winner' | 'elimination' | 'unchanged'; movieId?: number };

/** Observe completed gestures only. Never mutate ballots or run a second election. */
export function draftReaction(before: number[], after: number[], previous: RcvResult, current: RcvResult, movedId: number): CommentEvent | null {
  if (before.length === after.length && before.every((id, i) => id === after[i])) return null;
  if (previous.winner !== current.winner) return { type: 'winner', movieId: current.winner ?? undefined };
  const eliminated = (result: RcvResult) => result.rounds.map(round => round.eliminated).filter(id => id !== null).join(',');
  if (eliminated(previous) !== eliminated(current)) return { type: 'elimination' };
  const from = before.indexOf(movedId), to = after.indexOf(movedId);
  if (from >= 0 && to >= 0 && from !== to) return { type: to < from ? 'promoted' : 'demoted', movieId: movedId };
  return { type: 'unchanged' };
}

const lines: Record<CommentEvent['type'], string[]> = {
  welcome: ['I have reviewed the candidates. Several appear to be films.', 'Democracy detected. Adjusting expectations.', 'Your cinematic future is in your hands. Concerning.'],
  promoted: ['{film} moves up. Markets are responding irrationally.', 'A promotion for {film}. The lobbying budget was well spent.', '{film}, ascending. The trend line looks very busy.'],
  demoted: ['{film} moves down. Its campaign has requested privacy.', 'A setback for {film}. Blame the algorithm. Everyone does.', '{film} has been reorganised into a less important position.'],
  winner: ['Your draft now favours {film}. I hope you are pleased with yourself.', '{film} now wins this preview. A bold use of consequences.', 'New draft winner: {film}. Preparing my claim that I predicted this.'],
  elimination: ['You have rearranged the casualties. Very statesmanlike.', 'The elimination order changed. Someone is drafting a strongly worded pitch.'],
  unchanged: ['Same draft winner. Your political influence is largely decorative.', 'The furniture has moved. The institution endures.', 'The trend line remains confident. On what basis is unclear.'],
  submitted: ['Ballot filed. Accountability has entered the chat.', 'Your vote is saved. Your taste remains under review.', 'Another revision. Still one vote. I checked.'],
  failed: ['The ballot was not saved. Even bureaucracy has standards.', 'No receipt, no mandate. Please consult the actual error message.'],
  removed: ['The candidate list has been shortened by democracy. Please inspect the survivors.', 'A film has left the contest. Your draft requires another look.'],
  closed: ['Voting has ended. My consultancy continues to offer no value.', 'The polls are closed. Please direct further lobbying at the snacks.'],
};

export function createCommentator(movies: FilmLabel[], random: () => number = Math.random) {
  const panel = document.createElement('aside');
  panel.className = 'ai-commentator';
  panel.setAttribute('aria-label', 'LGFC AI commentary');
  // Decorative commentary must not compete with actual save/error announcements.
  panel.setAttribute('aria-live', 'off');
  const blob = document.createElement('span');
  blob.className = 'ai-blob'; blob.textContent = '• •'; blob.setAttribute('aria-hidden', 'true');
  const badge = document.createElement('strong'); badge.textContent = 'LGFC™ ✨AI✨';
  const bubble = document.createElement('p');
  panel.append(blob, badge, bubble);
  document.body.appendChild(panel);
  document.body.classList.add('has-commentator');
  let lastLine = '';
  return {
    react(event: CommentEvent): void {
      const choices = lines[event.type].filter(line => line !== lastLine);
      const index = Math.min(choices.length - 1, Math.max(0, Math.floor(random() * choices.length)));
      const line = choices[index]; lastLine = line;
      const id = 'movieId' in event ? event.movieId : undefined;
      const movie = movies.find(movie => movie.id === id);
      // Only public bootstrap labels; never fetch metadata or render titles as HTML.
      bubble.textContent = line.replace('{film}', movie ? filmLabel(movie) : 'no film');
    },
  };
}
