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

export function setupMovieDetails(movies: Movie[]): void {
  const dialog = document.querySelector<HTMLDialogElement>('#movie-dialog')!;
  const title = document.querySelector<HTMLElement>('#movie-dialog-title')!;
  const meta = document.querySelector<HTMLElement>('#movie-dialog-meta')!;
  const pitch = document.querySelector<HTMLElement>('#movie-dialog-pitch')!;
  const synopsis = document.querySelector<HTMLElement>('#movie-dialog-synopsis')!;
  const mystery = document.querySelector<HTMLElement>('#movie-dialog-mystery')!;
  const art = document.querySelector<HTMLElement>('#movie-dialog-art')!;
  document.querySelector('#close-movie-dialog')!.addEventListener('click', () => dialog.close());
  document.querySelector('#ranking-list')!.addEventListener('click', event => {
    const button = (event.target as HTMLElement).closest<HTMLElement>('[data-details]');
    if (!button) return;
    const movie = movies.find(item => item.id === Number(button.dataset.details));
    if (!movie) return;
    title.textContent = movie.title;
    meta.textContent = movie.is_mystery ? 'Mystery film' : [movie.release_year, movie.revealed ? 'Mystery revealed' : ''].filter(Boolean).join(' · ');
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
    dialog.showModal();
  });
  // Native dialog handles Escape, focus trapping and return to the opener.
  document.querySelectorAll<HTMLImageElement>('.movie-art img').forEach(image => {
    const fallback = () => { image.parentElement!.textContent = '▶'; };
    image.addEventListener('error', fallback, { once: true });
    if (image.complete && image.naturalWidth === 0) fallback();
  });
}
