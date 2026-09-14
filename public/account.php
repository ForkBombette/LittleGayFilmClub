<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Web;
Web::start(); $pdo=Database::connect(); $user=Auth::requireUser($pdo);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    Web::checkCsrf(); Auth::logout($pdo,(string)($_COOKIE[Auth::COOKIE] ?? ''));
    Auth::cookie('',time()-3600); $_SESSION=[]; session_regenerate_id(true);
    header('Location: login.php',true,303); exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your account</title><link rel="stylesheet" href="styles.css"></head><body><main><section><h1><?= Web::escape($user['display_name']) ?></h1><p><?= $user['role']==='organiser' ? 'Organiser' : 'Member' ?></p><a href="index.php">Back to voting</a>
<?php if ($user['role']==='organiser'): ?><p><a href="members.php">Manage members and login links</a></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><button>Sign out of this device</button></form></section></main></body></html>
