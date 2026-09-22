<?php
declare(strict_types=1);
namespace LGFC;

final class Navigation
{
    /** Shared, read-only navigation. Page permissions remain enforced by their handlers. */
    public static function render(array $viewer, string $page): void
    {
        $groups = [
            'vote' => ['Vote & results', 'index.php', ['movie-night.php' => 'Next movie night']],
            'films' => ['Films', 'movies.php', ['movies.php' => 'Nominations', 'watched.php' => 'Watched films', 'removals.php' => 'Removal votes']],
            'elections' => ['Elections', 'elections.php', ['elections.php' => 'Election history & controls']],
            'club' => ['Club', 'account.php', ['account.php' => 'Your account', 'credits.php' => 'Credits']],
        ];
        $night = MovieNights::current(Database::connect());
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/London')))->format('Y-m-d');
        if ($viewer['role'] === 'organiser') {
            $groups['club'][2] = ['account.php' => 'Your account', 'members.php' => 'Members & invitations', 'credits.php' => 'Credits'];
        }
        $active = match ($page) {
            'movies.php', 'edit-movie.php', 'watched.php', 'removals.php' => 'films',
            'elections.php', 'ballot-history.php' => 'elections',
            'account.php', 'members.php', 'credits.php' => 'club',
            default => 'vote',
        };
        ?>
        <a class="skip-link" href="#main-content">Skip to page content</a>
        <div class="site-shell">
        <header class="site-header">
            <div class="site-identity"><a class="site-brand" href="index.php">Little Gay Film Club™</a><span class="site-motto">What if chaos but democratic and gay?</span></div>
            <a class="account-link" href="account.php"><?= Web::escape($viewer['display_name']) ?><span><?= $viewer['role'] === 'organiser' ? 'Organiser' : 'Member' ?></span></a>
        </header>
        <nav class="primary-nav" aria-label="Main navigation">
            <?php foreach ($groups as $key => [$label, $href]): ?>
            <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="' . ($page === $href ? 'page' : 'location') . '"' : '' ?>><?= Web::escape($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <nav class="secondary-nav" aria-label="<?= Web::escape($groups[$active][0]) ?> navigation">
            <?php foreach ($groups[$active][2] as $href => $label): ?><a href="<?= $href ?>"<?= $page === $href ? ' aria-current="page"' : '' ?>><?= Web::escape($label) ?></a><?php endforeach; ?>
        </nav>
        <aside class="movie-night-banner" aria-label="Movie night announcement">
        <?php if ($night): ?>
            <p><strong><?= $night['scheduled_on'] < $today ? 'Last announced movie night' : 'Next movie night' ?></strong> · <time datetime="<?= Web::escape($night['scheduled_on']) ?>"><?= Web::escape((new \DateTimeImmutable($night['scheduled_on']))->format('l j F Y')) ?></time></p>
            <p><?= $night['source'] === 'election' ? 'The democratically selected film is ' : 'We’re watching ' ?><a href="movie-night.php#chosen-film"><?= Web::escape(FilmUi::label($night['movie'])) ?></a><?= $night['source'] === 'direct' ? ' · chosen directly by an organiser.' : '.' ?></p>
        <?php else: ?><p><strong>Next movie night</strong> · Date and film to be announced.</p><?php endif; ?>
        <?php if ($viewer['role'] === 'organiser'): ?><a href="movie-night.php">Manage the announcement</a><?php endif; ?>
        </aside>
        </div>
        <?php
    }
}
