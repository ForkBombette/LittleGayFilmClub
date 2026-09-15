import type { Movie } from './movie-details.js';
type Comment = { user_id: number; display_name: string; body: string; version: string; created_at: string; updated_at: string };
type ResponseData = { movie: Movie; comments: Comment[]; viewerId: number; csrf: string };
type Draft = { body: string; version: string };

export function setupComments(showMovie: (movie: Movie) => void): (movieId: number) => void {
  const form = document.querySelector<HTMLFormElement>('#comment-form')!;
  const field = document.querySelector<HTMLTextAreaElement>('#comment-body')!;
  const list = document.querySelector<HTMLElement>('#movie-comments')!;
  const message = document.querySelector<HTMLElement>('#comment-message')!;
  const save = document.querySelector<HTMLButtonElement>('#save-comment')!;
  const remove = document.querySelector<HTMLButtonElement>('#delete-comment')!;
  const refresh = document.querySelector<HTMLButtonElement>('#refresh-comments')!;
  const drafts = new Map<number, Draft>();
  let movieId = 0, sequence = 0, csrf = '', version = '', loaded = false;
  const busy = (value: boolean) => { field.disabled = save.disabled = remove.disabled = refresh.disabled = value; };
  function remember() { drafts.set(movieId, { body: field.value, version }); }
  field.addEventListener('input', remember);
  function render(data: ResponseData, preserve: boolean) {
    showMovie(data.movie);
    csrf = data.csrf;
    const own = data.comments.find(comment => comment.user_id === data.viewerId);
    const draft = preserve ? drafts.get(movieId) : undefined;
    version = draft?.version ?? own?.version ?? '';
    field.value = draft?.body ?? own?.body ?? '';
    remove.hidden = !own;
    list.replaceChildren();
    if (!data.comments.length) list.textContent = 'No comments yet. Make the case for—or against—this film.';
    for (const comment of data.comments) {
      const article = document.createElement('article'); article.className = 'film-comment';
      const author = document.createElement('h4'); author.textContent = comment.display_name + (comment.user_id === data.viewerId ? ' · You' : '');
      const body = document.createElement('p'); body.textContent = comment.body;
      const date = document.createElement('small'); date.textContent = comment.updated_at + ' UTC';
      article.append(author, body, date); list.append(article);
    }
    loaded = true;
    busy(false);
  }
  async function load(review = false) {
    const ticket = ++sequence, id = movieId;
    loaded = false; busy(true); message.textContent = 'Loading details and comments…';
    try {
      const response = await fetch(`movie-details.php?movieId=${id}`, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (ticket !== sequence) return;
      if (!response.ok) throw new Error(data.error ?? 'Could not load film details.');
      if (review && drafts.has(id)) {
        drafts.set(id, { body: drafts.get(id)!.body, version: data.comments.find((c: Comment) => c.user_id === data.viewerId)?.version ?? '' });
      }
      render(data, true);
      message.textContent = review ? 'Comments refreshed. Your unsaved draft, if any, has been kept for review.' : '';
    } catch (error) {
      if (ticket !== sequence) return;
      message.textContent = error instanceof Error ? error.message : 'Could not load comments. Please try again.';
      refresh.disabled = false;
    }
  }
  async function write(action: 'save' | 'delete') {
    if (!loaded) return;
    const id = movieId, ticket = ++sequence, submitted = { body: field.value, version };
    drafts.set(id, submitted);
    busy(true); message.textContent = action === 'save' ? 'Saving your comment…' : 'Deleting your comment…';
    try {
      const response = await fetch('movie-details.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ movieId: id, action, ...submitted }) });
      const data = await response.json();
      if (response.ok && drafts.get(id) === submitted) drafts.delete(id);
      if (ticket !== sequence) return;
      if (!response.ok) throw new Error(data.error ?? 'Could not save your comment.');
      render(data, false);
      message.textContent = action === 'save' ? 'Your comment is saved.' : 'Your comment has been deleted.';
    } catch (error) {
      if (ticket !== sequence) return;
      busy(false);
      message.textContent = error instanceof Error ? error.message : 'Could not save. Your draft is still here.';
    }
  }
  form.addEventListener('submit', event => { event.preventDefault(); void write('save'); });
  remove.addEventListener('click', () => { void write('delete'); });
  refresh.addEventListener('click', () => { void load(true); });
  return id => {
    movieId = id; version = drafts.get(id)?.version ?? ''; csrf = '';
    field.value = drafts.get(id)?.body ?? ''; remove.hidden = true; list.replaceChildren();
    void load();
  };
}
