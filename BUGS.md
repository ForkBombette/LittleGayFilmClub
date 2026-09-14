# Known bugs

## Duplicate entries when adding previously watched films

Status: open, low priority; deliberately parked for a future contributor.

Reported example: Conan the Destroyer appears three times as watched on the same day.

### Reproduce

1. As an organiser, open Watched films → Add a film we already watched.
2. Search for and import a film (or enter its details manually), choose a watched date, and save.
3. Repeat the new-film flow for the same film and date.

Actual: each save creates another catalogue entry and watched record for the same real film. The existing record is not detected.

Expected: recognise an existing catalogue film and guide the organiser to reuse or correct its watched record instead of silently creating a duplicate.

### Starting points for a fix

- Inspect `public/watched.php`, `src/Watched.php`, `src/Movies.php`, and `tests/watched_test.php`.
- The new-film flow creates a movie before recording its watch. One watched record per movie ID does not prevent multiple movie IDs representing the same film.
- Imported TMDB metadata is copied into ordinary fields; the provider ID is not currently persisted.
- Decide the matching policy before adding constraints: remakes and different films sharing a title must remain distinct. Any support for legitimate repeat screenings is a separate product decision.
- Add regression coverage for repeated imports/manual entry, existing-catalogue reuse, and same-title films from different years when implementing the fix.

For now, leave the implementation and existing duplicate records intact. This is a documented backlog item, not a request for automatic cleanup or a change to voting rules.
