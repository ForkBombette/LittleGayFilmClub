export type FilmLabel = { id: number; title: string; release_year?: number | null };
export function filmLabel(movie: FilmLabel): string {
  return movie.title + (movie.release_year ? ` (${movie.release_year})` : '');
}
/** Build sentence fragments safely; numeric fragments are film IDs, never raw HTML. */
export function filmText(parent: HTMLElement, parts: Array<string | number>, movies: FilmLabel[]): void {
  parent.replaceChildren();
  for (const part of parts) {
    const node = document.createElement(typeof part === 'number' ? 'button' : 'span');
    if (typeof part === 'number') {
      const movie = movies.find(movie => Number(movie.id) === part);
      node.textContent = movie ? filmLabel(movie) : `Movie ${part}`;
      node.setAttribute('type', 'button'); node.setAttribute('aria-haspopup', 'dialog');
      node.dataset.details = String(part); node.className = 'film-title';
    } else node.textContent = part;
    parent.appendChild(node);
  }
}
