<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command-line only.'); }

$root = dirname(__DIR__);
$varDir = $root . DIRECTORY_SEPARATOR . 'var';
$dbPath = $varDir . DIRECTORY_SEPARATOR . 'lgfc.sqlite';

if (!is_dir($varDir) && !mkdir($varDir, 0777, true) && !is_dir($varDir)) {
    throw new RuntimeException('Could not create var directory.');
}

if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/seed.sql'));

echo "Created {$dbPath}\n";
