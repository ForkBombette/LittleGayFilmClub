<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Elections;
use LGFC\Movies;
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS - {$label}\n";
}
function rejects(callable $operation, string $class, string $label): void {
    try { $operation(); } catch (Throwable $error) { check($error instanceof $class, $label); return; }
    throw new RuntimeException($label . ' (unexpected success)');
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__ . '/../db/schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/../db/seed.sql'));
Elections::migrate($pdo);
$ids = Elections::candidateIds($pdo, 1);
rejects(fn() => Elections::open($pdo, 'Duplicate'), DomainException::class, 'cannot open a second election');
rejects(fn() => Elections::submit($pdo, 999, 1, $ids), InvalidArgumentException::class, 'unknown election rejected');
rejects(fn() => Elections::submit($pdo, 1, 999, $ids), InvalidArgumentException::class, 'unknown voter rejected');
$pdo->exec('UPDATE users SET is_active = 0 WHERE id = 1');
rejects(fn() => Elections::submit($pdo, 1, 1, $ids), InvalidArgumentException::class, 'inactive voter rejected');
$pdo->exec('UPDATE users SET is_active = 1 WHERE id = 1');
rejects(fn() => Elections::submit($pdo, 1, 1, [$ids[0], $ids[0]]), InvalidArgumentException::class, 'duplicate or incomplete ranking rejected');
rejects(fn() => Elections::submit($pdo, 1, 1, ['1',2,3,4,5,6]), InvalidArgumentException::class, 'noninteger ranking rejected');
$first = Elections::submit($pdo, 1, 1, $ids);
$last = Elections::submit($pdo, 1, 1, array_reverse($ids));
Elections::submit($pdo, 1, 2, array_reverse($ids));
check(count(Elections::ballots($pdo, 1)) === 2 && $last > $first, 'revisions still count as one vote per user');
$late = Movies::nominate($pdo, 1, ['title' => 'Private film title', 'mystery' => '1', 'alias' => 'A mystery', 'pitch' => 'Trust the pitch']);
check(Elections::candidateIds($pdo, 1) === $ids, 'late nomination cannot alter open snapshot');
$result = Elections::currentResult($pdo, 1);
Elections::close($pdo, 1);
$stored = $pdo->query('SELECT * FROM election_results WHERE election_id = 1')->fetch();
check(Elections::finalResult($pdo, 1) === $result, 'close freezes authoritative rounds and winner');
check(json_decode($stored['ballot_revision_ids_json'], true)[0] === $last, 'freeze records the exact latest revisions');
check(!str_contains($stored['result_json'], 'Private film'), 'stored results contain IDs, not private metadata');
$before = $pdo->query('SELECT COUNT(*) FROM ballot_revisions')->fetchColumn();
rejects(fn() => Elections::submit($pdo, 1, 1, $ids), DomainException::class, 'stale ballot rejected after close');
check($before === $pdo->query('SELECT COUNT(*) FROM ballot_revisions')->fetchColumn(), 'rejected ballot creates no revision');
Elections::close($pdo, 1);
check($stored === $pdo->query('SELECT * FROM election_results WHERE election_id = 1')->fetch(), 'repeat close preserves the original frozen result');
$pdo->exec("UPDATE movies SET status = 'watched' WHERE id = 1; UPDATE movies SET status = 'removed' WHERE id = 2");
$next = Elections::open($pdo, 'Next night');
check(Elections::candidateIds($pdo, $next) === [3,4,5,6,$late], 'new election snapshots active pool including late mystery');
check(Elections::ballots($pdo, $next) === [], 'new election starts with no carried-over votes');
check(Elections::candidateIds($pdo, 1) === $ids && Elections::finalResult($pdo, 1) === $result, 'new election and movie status changes preserve old result');
check($pdo->query('SELECT revealed_at FROM movies WHERE id = ' . $late)->fetchColumn() === null, 'election lifecycle does not reveal mystery');
Elections::close($pdo, $next);
check(Elections::finalResult($pdo, $next) === ['winner' => null, 'rounds' => []], 'closing with zero votes stores no winner');
$pdo->exec("UPDATE movies SET status = 'watched'");
rejects(fn() => Elections::open($pdo, 'Empty pool'), DomainException::class, 'empty candidate pool cannot open');
rejects(fn() => Elections::open($pdo, ' '), InvalidArgumentException::class, 'blank name rejected');
// Simulate a legacy closed election and verify repeatable backfill.
$pdo->exec('DELETE FROM election_results WHERE election_id = 1');
Elections::migrate($pdo);
$backfilled = Elections::finalResult($pdo, 1);
Elections::migrate($pdo);
check($backfilled === $result && Elections::finalResult($pdo, 1) === $result, 'migration backfills legacy closure once');

// Two real SQLite connections test the shared submit/close write-lock boundary.
$path = tempnam(sys_get_temp_dir(), 'lgfc-lock-');
try {
    $one = new PDO('sqlite:' . $path);
    $two = new PDO('sqlite:' . $path);
    foreach ([$one, $two] as $connection) {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA busy_timeout = 1');
    }
    $one->exec(file_get_contents(__DIR__ . '/../db/schema.sql'));
    $one->exec(file_get_contents(__DIR__ . '/../db/seed.sql'));
    $one->exec('BEGIN IMMEDIATE');
    rejects(fn() => Elections::submit($two, 1, 1, $ids), PDOException::class, 'submit cannot cross a concurrent lifecycle write lock');
    rejects(fn() => Elections::open($two, 'Concurrent open'), PDOException::class, 'open uses the same exclusive writer boundary');
    $one->exec('ROLLBACK');
    Elections::submit($two, 1, 1, $ids);
    Elections::close($one, 1);
    rejects(fn() => Elections::submit($two, 1, 2, $ids), DomainException::class, 'second connection rechecks closed status');
    check(Elections::finalResult($one, 1)['winner'] === $ids[0], 'ballot committed before closure is included');
} finally {
    $one = $two = $connection = null;
    unlink($path);
}
