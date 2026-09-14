<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Database;
use LGFC\Elections;
use LGFC\Auth;
use LGFC\Web;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); http_response_code(405); echo json_encode(['error' => 'Use POST to submit a ballot.']); exit;
}
$pdo = Database::connect();
Web::start();
$viewer = Auth::requireUser($pdo, true);
Web::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '', true);
$payload = json_decode(file_get_contents('php://input'), true);
$userId = $viewer['id'];
$electionId = filter_var($payload['electionId'] ?? null, FILTER_VALIDATE_INT);
$ranking = $payload['ranking'] ?? null;
if (!$userId || !$electionId || !is_array($ranking)) {
    http_response_code(400); echo json_encode(['error' => 'Missing or invalid ballot data.']); exit;
}
try {
    $revision = Elections::submit($pdo, $electionId, $userId, $ranking);
    echo json_encode(['ok' => true, 'revisionId' => $revision]);
} catch (LGFC\CandidatesChanged $error) {
    http_response_code(409); echo json_encode(['error' => $error->getMessage(), 'code' => 'candidates_changed', 'candidateIds' => $error->candidateIds]);
} catch (DomainException $error) {
    http_response_code(409); echo json_encode(['error' => $error->getMessage(), 'code' => 'election_closed']);
} catch (InvalidArgumentException $error) {
    http_response_code(400); echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    http_response_code(500); echo json_encode(['error' => 'Could not save ballot. Please try again.']);
}
