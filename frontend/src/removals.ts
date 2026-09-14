import type { Movie } from './movie-details.js';

/** Apply only candidate exclusions; never refresh other members' ballot snapshots. */
export function reconcileCandidates(movies: Movie[], ranking: number[], eligibleIds: number[]) {
  const eligible = new Set(eligibleIds);
  return {
    movies: movies.filter(movie => eligible.has(movie.id)),
    ranking: ranking.filter(id => eligible.has(id)),
    removed: movies.filter(movie => !eligible.has(movie.id)),
  };
}
