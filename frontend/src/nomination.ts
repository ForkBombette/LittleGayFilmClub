import { setupMetadataSearch } from './metadata-search.js';
setupMetadataSearch();

import { setupMovieDetails, type Movie } from './movie-details.js';
const catalogue = document.querySelector('#catalogue-data');
if (catalogue) setupMovieDetails(JSON.parse(catalogue.textContent || '[]') as Movie[]);
