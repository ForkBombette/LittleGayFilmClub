<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Watched;
use LGFC\Movies;
use LGFC\Elections;
function check(bool $value, string $label): void { if (!$value) throw new RuntimeException($label); echo "PASS - {$label}\n"; }
function rejects(callable $operation, string $label): void {
    try { $operation(); } catch (InvalidArgumentException $error) { check(true, $label); return; }
    throw new RuntimeException($label);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__ . '/../db/schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/../db/seed.sql'));
Watched::migrate($pdo); Watched::migrate($pdo);
$ids = Elections::candidateIds($pdo, 1);
Elections::submit($pdo, 1, 1, $ids);
$revisions = $pdo->query('SELECT * FROM ballot_revisions')->fetchAll();
Watched::record($pdo, 1, 1, ['watched_on' => '2020-02-29']);
check(Elections::candidateIds($pdo, 1) === $ids, 'marking watched keeps open ballot intact');
Elections::close($pdo, 1);
$final = Elections::finalResult($pdo, 1);
$offBallot = Watched::record($pdo, 2, null, ['title' => 'A past independent choice', 'year' => '1980', 'election_id' => '1']);
check(!in_array($offBallot, $ids) && Elections::finalResult($pdo, 1) === $final, 'off-ballot film can link an election without altering winner');
check($revisions === $pdo->query('SELECT * FROM ballot_revisions')->fetchAll(), 'watched records never manufacture votes');
check($pdo->query('SELECT status FROM movies WHERE id = ' . $offBallot)->fetchColumn() === 'watched', 'new historical film is immediately watched');
check($pdo->query('SELECT watched_on FROM movie_watches WHERE movie_id = ' . $offBallot)->fetchColumn() === null, 'unknown date stays null');
$next = Elections::open($pdo, 'Next');
check(!in_array(1, Elections::candidateIds($pdo, $next)) && !in_array($offBallot, Elections::candidateIds($pdo, $next)), 'future elections exclude watched films');
Watched::record($pdo, 1, 1, ['watched_on' => '2021-03-04']);
check((int) $pdo->query('SELECT COUNT(*) FROM movie_watches WHERE movie_id = 1')->fetchColumn() === 1, 'correction keeps one watched record per film');
check($pdo->query('SELECT watched_on FROM movie_watches WHERE movie_id = 1')->fetchColumn() === '2021-03-04', 'date correction saved');
$mystery = Movies::nominate($pdo, 1, ['title' => 'Secret watched title', 'mystery' => '1', 'alias' => 'The surprise', 'pitch' => 'Public pitch']);
Watched::record($pdo, 2, $mystery, []);
$history = Watched::history($pdo);
check(!str_contains(json_encode($history), 'Secret watched title') && str_contains(json_encode($history), 'The surprise'), 'watched history keeps mystery identity private');
check($pdo->query('SELECT revealed_at FROM movies WHERE id = ' . $mystery)->fetchColumn() === null, 'watching does not reveal');
$pdo->exec("UPDATE movies SET status = 'watched' WHERE id = 2");
check(in_array(2, array_map(fn($entry) => $entry['movie']['id'], Watched::history($pdo))), 'legacy watched movies remain in history');
rejects(fn() => Watched::record($pdo, 1, 3, ['watched_on' => '2021-02-29']), 'invalid calendar date rejected');
rejects(fn() => Watched::record($pdo, 1, 3, ['watched_on' => '2999-01-01']), 'future date rejected');
rejects(fn() => Watched::record($pdo, 1, 9999, []), 'unknown movie rejected');
rejects(fn() => Watched::record($pdo, 9999, 3, []), 'unknown recorder rejected');
$count = $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
rejects(fn() => Watched::record($pdo, 1, null, ['title' => 'Invalid relation', 'election_id' => '9999']), 'invalid election rejects new film atomically');
check($count === $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn(), 'failed operation leaves no orphan movie');
rejects(fn() => Watched::record($pdo, 1, null, ['title' => '']), 'blank historical title rejected');
check($final === Elections::finalResult($pdo, 1), 'stored election outcome remains unchanged');
