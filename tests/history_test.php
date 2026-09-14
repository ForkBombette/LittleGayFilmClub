<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\{BallotHistory, Elections};
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS - $label\n";
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__ . '/../db/schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/../db/seed.sql'));
check(BallotHistory::snapshot($pdo, 1)['steps'] === [], 'empty election has no invented submissions');
try { BallotHistory::snapshot($pdo, 999); throw new RuntimeException('missing election accepted'); }
catch (InvalidArgumentException $error) { check(!$pdo->inTransaction(), 'missing election releases read transaction'); }
$ids = Elections::candidateIds($pdo, 1);
$a = Elections::submit($pdo, 1, 1, $ids);
$b = Elections::submit($pdo, 1, 2, array_reverse($ids));
$c = Elections::submit($pdo, 1, 1, array_reverse($ids));
$pdo->exec("UPDATE ballot_revisions SET created_at='2026-01-01 12:00:00'");
$h = BallotHistory::snapshot($pdo, 1);
check(array_column($h['steps'], 'id') === [$a,$b,$c], 'same-second submissions retain ID order');
check(array_column($h['steps'], 'version') === [1,1,2], 'revision numbers are per voter');
check(array_column($h['steps'], 'voters') === [1,2,2], 'revision replaces a ballot without adding a vote');
check($h['steps'][0]['result']['winner'] === 1 && $h['steps'][2]['result']['winner'] === 6, 'revisions can change the winner');
check($h['steps'][2]['result'] === Elections::currentResult($pdo, 1), 'latest step agrees with authoritative calculation');
$pdo->exec("UPDATE users SET is_active=0 WHERE id=2; UPDATE movies SET status='watched' WHERE id=1");
check(BallotHistory::snapshot($pdo, 1)['steps'] === $h['steps'], 'deactivation and watched status retain historical votes and eligibility');
$pdo->exec("UPDATE movies SET title='SECRET REAL TITLE', mystery_alias='Mystery <film>', summary='SECRET SUMMARY', image_url='https://example.org/secret.jpg' WHERE id=6");
$h = BallotHistory::snapshot($pdo, 1);
check($h['movies'][6] === 'Mystery <film>' && !str_contains(json_encode($h), 'SECRET') && !str_contains(json_encode($h), 'secret.jpg'), 'history contains only public mystery labels');
$pdo->exec('INSERT INTO election_removals(election_id,movie_id) VALUES(1,6)');
$h = BallotHistory::snapshot($pdo, 1);
check($h['removed'] === [6] && $h['steps'][2]['ranking'][0] === 6, 'removed films remain in original rankings');
check($h['steps'][2]['result'] === Elections::currentResult($pdo, 1) && $h['steps'][2]['result']['winner'] !== 6, 'recalculations skip removed films');
Elections::close($pdo, 1);
$h = BallotHistory::snapshot($pdo, 1);
check($h['final'] === Elections::finalResult($pdo, 1), 'closed history exposes stored final result');
// Simulate stray imported rows beyond the frozen revisions, including a new voter.
$pdo->exec('INSERT INTO ballot_revisions(election_id,user_id) VALUES(1,1),(1,3)');
check(BallotHistory::snapshot($pdo, 1) === $h, 'closed history stops at exact per-user frozen revisions');
$next = Elections::open($pdo, 'Next');
check(BallotHistory::snapshot($pdo, $next)['steps'] === [], 'election histories are isolated');
Elections::close($pdo, $next);
check(BallotHistory::snapshot($pdo, $next)['final'] === ['winner'=>null,'rounds'=>[]], 'empty closed election retains no-winner result');
$pdo->exec('INSERT OR IGNORE INTO election_removals(election_id,movie_id) SELECT election_id,movie_id FROM election_movies WHERE election_id=1');
$empty = BallotHistory::snapshot($pdo, 1);
check($empty['steps'][2]['result'] === ['winner'=>null,'rounds'=>[]] && $empty['final'] === $h['final'], 'zero candidates has no calculated winner and cannot rewrite final result');
