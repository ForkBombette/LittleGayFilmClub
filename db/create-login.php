<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/src/bootstrap.php';
$options=getopt('', ['user:','base-url:','output:']);
$name=$options['user'] ?? ''; $base=$options['base-url'] ?? ''; $output=$options['output'] ?? '';
if (!$name || !filter_var($base,FILTER_VALIDATE_URL) || !in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true) || !$output) {
    fwrite(STDERR,"Usage: php db/create-login.php --user NAME --base-url URL_TO_PUBLIC --output PRIVATE_HTML_FILE\n"); exit(1);
}
// Accept either the public directory or its sign-in page; never append twice.
if (parse_url($base, PHP_URL_QUERY) !== null || parse_url($base, PHP_URL_FRAGMENT) !== null) {
    fwrite(STDERR, "Use the public directory URL without a query string or secret fragment.\n"); exit(1);
}
$base = preg_replace('~/login\.php$~i', '', rtrim($base, '/'));
$pdo=LGFC\Database::connect();
$stmt=$pdo->prepare('SELECT id FROM users WHERE display_name=? AND is_active=1'); $stmt->execute([$name]); $id=$stmt->fetchColumn();
if (!$id) { fwrite(STDERR,"Active user not found.\n"); exit(1); }
$stmt=$pdo->prepare("UPDATE users SET role='organiser' WHERE id=?"); $stmt->execute([$id]);
$token=LGFC\Auth::issue($pdo,(int)$id,(int)$id);
$link=rtrim($base,'/').'/login.php#'.$token;
$html='<!doctype html><meta charset="utf-8"><title>Film club sign-in</title><p>Private, single-use organiser link for '.htmlspecialchars($name,ENT_QUOTES).'. Expires in seven days.</p><a href="'.htmlspecialchars($link,ENT_QUOTES).'">Sign in to the film club</a>';
if (file_put_contents($output,$html)===false) { fwrite(STDERR,"Could not write private login file.\n"); exit(1); }
echo "Organiser link written to: " . (realpath($output) ?: $output) . "\nOpen or reload that local HTML file, then follow its new link. Older unused links have been replaced.\n";
