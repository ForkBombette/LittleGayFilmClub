export type FilmMetadata = { id: number; title: string; year: string; summary: string; image_url: string };

/** Import only descriptive fields. Pitch, mystery controls and identity stay untouched. */
export function applyMetadata(form: HTMLFormElement, film: FilmMetadata): void {
  for (const name of ['title', 'year', 'summary', 'image_url'] as const) {
    const field = form.elements.namedItem(name) as HTMLInputElement | HTMLTextAreaElement;
    field.value = film[name];
  }
}

export function setupMetadataSearch(options: { target?: string; review?: string } = {}): void {
  const search = document.querySelector<HTMLFormElement>('#metadata-search');
  const target = document.querySelector<HTMLFormElement>(options.target ?? '#nomination-form');
  if (!search || !target) return;
  const message = document.querySelector<HTMLParagraphElement>('#metadata-message')!;
  const results = document.querySelector<HTMLDivElement>('#metadata-results')!;
  const button = search.querySelector<HTMLButtonElement>('button')!;
  let request = 0;
  search.addEventListener('submit', async event => {
    event.preventDefault();
    const current = ++request;
    const fields = new FormData(search);
    const csrf = (target.elements.namedItem('csrf') as HTMLInputElement).value;
    results.replaceChildren();
    button.disabled = true;
    message.textContent = 'Searching…';
    try {
      const response = await fetch('search-movies.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ query: fields.get('query'), year: fields.get('year') }),
      });
      const data = await response.json();
      if (request !== current) return;
      if (!response.ok) { message.textContent = data.error ?? 'Search failed. You can still enter the film manually.'; return; }
      const films: FilmMetadata[] = data.results;
      message.textContent = films.length ? 'Choose the correct film and release year. Refine your search if it is not listed.' : 'No films found. Try another title or year, or enter the details manually.';
      for (const film of films) {
        const article = document.createElement('article'); article.className = 'pool-movie';
        const title = document.createElement('h3'); title.textContent = `${film.title} (${film.year || 'year unknown'})`; article.append(title);
        if (film.image_url) {
          const poster = document.createElement('img'); poster.src = film.image_url; poster.alt = ''; poster.width = 90;
          poster.loading = 'lazy'; poster.referrerPolicy = 'no-referrer'; article.append(poster);
        }
        const summary = document.createElement('p'); summary.textContent = film.summary || 'No synopsis available.'; article.append(summary);
        const use = document.createElement('button'); use.type = 'button'; use.textContent = 'Use these details';
        use.addEventListener('click', () => {
          applyMetadata(target, film);
          message.textContent = `Details copied for ${film.title}. ${options.review ?? 'Check the nomination form, add your pitch and choose whether it is a mystery, then save.'}`;
          (target.elements.namedItem('title') as HTMLInputElement).focus();
        });
        article.append(use); results.append(article);
      }
    } catch {
      if (request === current) message.textContent = 'Search failed. Your form has not changed; you can enter the film manually.';
    } finally {
      if (request === current) button.disabled = false;
    }
  });
}
