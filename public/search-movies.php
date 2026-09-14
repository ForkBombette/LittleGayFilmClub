<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\MovieMetadata;
use LGFC\Web;
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');http_response_code(405);echo json_encode(['error'=>'Use POST to search.']);exit;}
Web::start();Auth::requireUser(Database::connect(),true);Web::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??'',true);
$payload=json_decode(file_get_contents('php://input'),true);
if(!is_string($payload['query']??null) || !is_string($payload['year']??'')){http_response_code(400);echo json_encode(['error'=>'Enter a film title and optional year.']);exit;}
try {
    // Search only on explicit request. No local film ID or private catalogue lookup is accepted.
    $results=MovieMetadata::configured()->search($payload['query'],$payload['year']??'');
    echo json_encode(['results'=>$results],JSON_THROW_ON_ERROR);
}catch(DomainException $error){http_response_code(400);echo json_encode(['error'=>$error->getMessage()]);}
catch(Throwable $error){http_response_code(503);echo json_encode(['error'=>'Movie search is unavailable. You can enter the film manually.']);}
