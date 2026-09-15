<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\{Auth, Comments, Database, Movies, Web};
header('Content-Type: application/json; charset=utf-8');
Web::start();
$pdo = Database::connect();
$viewer = Auth::requireUser($pdo, true);
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); http_response_code(405); echo json_encode(['error'=>'Use GET or POST.']); exit; }
try {
    $payload = $method === 'POST' ? json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR) : $_GET;
    $id = filter_var($payload['movieId'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new InvalidArgumentException('Choose a film.');
    $stmt = $pdo->prepare('SELECT * FROM movies WHERE id=?'); $stmt->execute([$id]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$movie) { http_response_code(404); echo json_encode(['error'=>'Film not found.']); exit; }
    if ($method === 'POST') {
        Web::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '', true);
        if (!in_array($payload['action'] ?? null, ['save','delete'], true) || !is_string($payload['body'] ?? '') || !is_string($payload['version'] ?? null)) {
            throw new InvalidArgumentException('Choose a valid comment action.');
        }
        // The actor always comes from the authenticated session; there is no target-user parameter.
        Comments::write($pdo, $id, $viewer['id'], $payload['body'] ?? '', $payload['version'], $payload['action'] === 'delete');
    }
    echo json_encode(['movie'=>Movies::publicView($movie),'comments'=>Comments::listing($pdo,$id),
        'viewerId'=>$viewer['id'],'csrf'=>$_SESSION['csrf']], JSON_THROW_ON_ERROR);
} catch (DomainException $error) { http_response_code(409); echo json_encode(['error'=>$error->getMessage()]); }
catch (InvalidArgumentException | JsonException $error) { http_response_code(400); echo json_encode(['error'=>'Invalid comment request. ' . ($error instanceof InvalidArgumentException ? $error->getMessage() : 'Please try again.')]); }
catch (Throwable $error) { http_response_code(503); echo json_encode(['error'=>'Film details or comments are unavailable. Your draft has not been cleared. Please try again.']); }
