<?php
declare(strict_types=1);
namespace LGFC;
final class FilmUi
{
    /** Accept only publicView data; mysteries must never receive private labels. */
    public static function label(array $movie): string
    {
        return $movie['title'] . (($movie['release_year'] ?? null) !== null && empty($movie['is_mystery']) ? ' (' . (int)$movie['release_year'] . ')' : '');
    }
    public static function button(int $id, string $label): string
    {
        return '<button type="button" class="film-title" data-details="' . $id . '" aria-haspopup="dialog">' . Web::escape($label) . '</button>';
    }
    public static function movie(array $movie): string { return self::button((int)$movie['id'], self::label($movie)); }
}
