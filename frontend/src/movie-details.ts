import { setupComments } from './movie-comments.js';
export type Movie = {
  id: number;
  title: string;
  release_year: number | null;
  image_url: string | null;
  summary: string | null;
  nomination_pitch: string | null;
  nominator_id: number | null;
  is_mystery: boolean;
  revealed: boolean;
};

let initialized = false;
export function setupMovieDetails(movies: Movie[] = []): void {
  if (initialized) return;
  initialized = true;
  const dialog = document.querySelector<HTMLDialogElement>('#movie-dialog')!;
  const title = document.querySelector<HTMLElement>('#movie-dialog-title')!;
  const meta = document.querySelector<HTMLElement>('#movie-dialog-meta')!;
  const pitch = document.querySelector<HTMLElement>('#movie-dialog-pitch')!;
  const synopsis = document.querySelector<HTMLElement>('#movie-dialog-synopsis')!;
  const mystery = document.querySelector<HTMLElement>('#movie-dialog-mystery')!;
  const art = document.querySelector<HTMLElement>('#movie-dialog-art')!;
  document.querySelector('#close-movie-dialog')!.addEventListener('click', () => dialog.close());
  function showMovie(movie: Movie) {
    title.textContent = movie.title + (movie.release_year ? ` (${movie.release_year})` : '');
    meta.textContent = movie.is_mystery ? 'Mystery film' : (movie.revealed ? 'Mystery revealed' : '');
    pitch.textContent = movie.nomination_pitch || 'No pitch yet.';
    synopsis.hidden = movie.is_mystery;
    synopsis.querySelector('p')!.textContent = movie.summary || 'No synopsis added yet.';
    mystery.hidden = !movie.is_mystery;
    art.replaceChildren();
    if (movie.image_url && !movie.is_mystery) {
      const image = document.createElement('img');
      image.src = movie.image_url;
      image.alt = '';
      image.referrerPolicy = 'no-referrer';
      image.addEventListener('error', () => { art.textContent = '▶'; }, { once: true });
      art.appendChild(image);
    } else art.textContent = movie.is_mystery ? '?' : '▶';
  }
  const openComments = setupComments(showMovie);
  document.querySelectorAll<HTMLButtonElement>('[data-film-select]').forEach(button => {
    const select = document.getElementById(button.dataset.filmSelect!) as HTMLSelectElement;
    const sync = () => { button.disabled = !select.value; };
    select.addEventListener('change', sync); sync();
  });
  document.querySelector('main')!.addEventListener('click', event => {
    const button = (event.target as HTMLElement).closest<HTMLElement>('[data-details], [data-film-select]');
    if (!button) return;
    const selected = button.dataset.filmSelect ? document.getElementById(button.dataset.filmSelect) as HTMLSelectElement : null;
    const id = Number(selected ? selected.value : button.dataset.details);
    if (!id) return;
    const movie = movies.find(item => item.id === id);
    title.textContent = 'Loading film…'; meta.textContent = ''; pitch.textContent = ''; art.replaceChildren();
    synopsis.hidden = true; mystery.hidden = true;
    if (movie) showMovie(movie);
    dialog.showModal();
    openComments(id);
  });
  // Native dialog handles Escape, focus trapping and return to the opener.
  document.querySelectorAll<HTMLImageElement>('.movie-art img').forEach(image => {
    const fallback = () => { image.parentElement!.textContent = '▶'; };
    image.addEventListener('error', fallback, { once: true });
    if (image.complete && image.naturalWidth === 0) fallback();
  });
}
