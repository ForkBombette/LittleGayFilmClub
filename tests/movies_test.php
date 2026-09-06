<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Movies;
use LGFC\MovieSchema;
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS - {$label}\n";
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(file_get_contents(__DIR__ . '/../db/schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/../db/seed.sql'));
MovieSchema::migrate($pdo);
MovieSchema::migrate($pdo);
$eligibleBefore = $pdo->query('SELECT * FROM election_movies')->fetchAll();
$id = Movies::nominate($pdo, 1, ['title' => 'Hidden sentinel title', 'year' => '1980', 'image_url' => 'https://example.com/secret-poster.jpg', 'summary' => 'Hidden sentinel synopsis', 'mystery' => '1', 'alias' => 'A questionable adventure', 'pitch' => 'Trust this pitch.']);
$read = static function () use ($pdo, $id): array { return $pdo->query('SELECT * FROM movies WHERE id = ' . $id)->fetch(); };
$public = Movies::publicView($read());
check($public['title'] === 'A questionable adventure' && $public['is_mystery'], 'mystery displays its alias');
check($public['image_url'] === null && $public['summary'] === null && $public['release_year'] === null, 'private metadata omitted');
check(!str_contains(json_encode($public), 'Hidden sentinel') && !str_contains(json_encode($public), 'secret-poster'), 'serialized browser payload has no spoilers');
check($eligibleBefore === $pdo->query('SELECT * FROM election_movies')->fetchAll(), 'nomination preserves election snapshot');
$pdo->exec("UPDATE elections SET status = 'closed'");
check(Movies::publicView($read())['is_mystery'], 'closing election does not reveal');
try { Movies::reveal($pdo, $id, 2); throw new RuntimeException('Wrong owner revealed'); } catch (InvalidArgumentException $e) {}
check(Movies::publicView($read())['is_mystery'], 'wrong nominator cannot reveal');
$pdo->exec('UPDATE users SET is_active = 0 WHERE id = 1');
try { Movies::reveal($pdo, $id, 1); throw new RuntimeException('Inactive owner revealed'); } catch (InvalidArgumentException $e) {}
$pdo->exec('UPDATE users SET is_active = 1 WHERE id = 1');
Movies::reveal($pdo, $id, 1);
$timestamp = $read()['revealed_at'];
Movies::reveal($pdo, $id, 1);
check($timestamp === $read()['revealed_at'], 'repeated reveal is idempotent');
$public = Movies::publicView($read());
check(!$public['is_mystery'] && $public['title'] === 'Hidden sentinel title' && $public['release_year'] === 1980, 'deliberate reveal works after election closes');
check($public['summary'] === 'Hidden sentinel synopsis' && $public['nomination_pitch'] === 'Trust this pitch.', 'reveal restores metadata and keeps pitch');
check(Movies::safeImage('javascript:alert(1)') === null && Movies::safeImage('//example.com/x') === null && Movies::safeImage('images/../var/x') === null, 'unsafe image URLs rejected');
try { Movies::nominate($pdo, 1, ['title' => 'A title', 'mystery' => '1']); throw new RuntimeException('Missing pitch accepted'); } catch (InvalidArgumentException $e) {}
check(true, 'mystery requires alias and pitch');
// Upgrade the exact old shape, with a row that must survive unchanged.
$old = new PDO('sqlite::memory:');
$old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$old->exec('CREATE TABLE users (id INTEGER PRIMARY KEY); CREATE TABLE movies (id INTEGER PRIMARY KEY, title TEXT); INSERT INTO movies VALUES (1, "Kept");');
MovieSchema::migrate($old);
MovieSchema::migrate($old);
check($old->query('SELECT title FROM movies')->fetchColumn() === 'Kept', 'upgrade is repeatable and preserves existing movies');
