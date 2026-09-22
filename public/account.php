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
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your account</title><link rel="stylesheet" href="styles.css"></head><body><?php LGFC\Navigation::render($user, 'account.php'); ?><main id="main-content" tabindex="-1"><header><p class="eyebrow">Club business</p><h1>Your account</h1><p>Membership, invitations and the paperwork behind movie night.</p></header><section><h2><?= Web::escape($user['display_name']) ?></h2><p><?= $user['role']==='organiser' ? 'Organiser' : 'Member' ?></p>
<?php if ($user['role']==='organiser'): ?><p><a href="members.php">Manage members and login links</a></p><?php endif; ?>
<h1>What to do</h1><p>On the <strong>Vote & Results</strong> page you can see the current election, cast your vote and view the results of past elections. The <strong>Films</strong> page shows the catalogue and lets you nominate more films for consideration - you can even make them a mystery, but you'd better make a strong case. And we can propose a film for removal. If a simple majority agree - it's gone.</p><p>Anywhere you see a film title you can click for details, and leave a comment either making the case for it in an election or reviewing it once we've seen it</p>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><button>Sign out of this device</button></form></section></main></body></html>
