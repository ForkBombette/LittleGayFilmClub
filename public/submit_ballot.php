<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use LGFC\Database;

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON.']);
    exit;
}

$userId = filter_var($payload['userId'] ?? null, FILTER_VALIDATE_INT);
$electionId = filter_var($payload['electionId'] ?? null, FILTER_VALIDATE_INT);
$ranking = $payload['ranking'] ?? null;

if (!$userId || !$electionId || !is_array($ranking) || $ranking === []) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid ballot data.']);
    exit;
}

$ranking = array_values(array_map('intval', $ranking));
if (count($ranking) !== count(array_unique($ranking))) {
    http_response_code(400);
    echo json_encode(['error' => 'A movie may only appear once in a ballot.']);
    exit;
}

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT movie_id FROM election_movies WHERE election_id = :election_id');
$stmt->execute(['election_id' => $electionId]);
$eligible = array_map('intval', array_column($stmt->fetchAll(), 'movie_id'));
sort($eligible);
$submitted = $ranking;
sort($submitted);

if ($eligible !== $submitted) {
    http_response_code(400);
    echo json_encode(['error' => 'Ballot must rank every eligible movie exactly once.']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO ballot_revisions (election_id, user_id) VALUES (:election_id, :user_id)');
    $stmt->execute(['election_id' => $electionId, 'user_id' => $userId]);
    $revisionId = (int) $pdo->lastInsertId();

    $choice = $pdo->prepare(
        'INSERT INTO ballot_revision_choices (ballot_revision_id, movie_id, rank)
         VALUES (:revision_id, :movie_id, :rank)'
    );

    foreach ($ranking as $index => $movieId) {
        $choice->execute([
            'revision_id' => $revisionId,
            'movie_id' => $movieId,
            'rank' => $index + 1,
        ]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'revisionId' => $revisionId]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not save ballot.']);
}
