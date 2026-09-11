<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Web;
Web::start();
if (!Auth::transportAllowed()) { http_response_code(403); exit('Please use HTTPS to sign in.'); }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    Web::checkCsrf();
    try {
        $pdo=Database::connect();
        $token=Auth::exchange($pdo,is_string($_POST['token'] ?? null) ? $_POST['token'] : '');
        Auth::logout($pdo,(string)($_COOKIE[Auth::COOKIE] ?? ''));
        session_regenerate_id(true);
        $_SESSION=[];
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        Auth::cookie($token,time()+Auth::SESSION_SECONDS);
        header('Location: index.php',true,303); exit;
    } catch (DomainException $exception) { $error=$exception->getMessage(); }
    catch (Throwable $exception) { $error='Could not sign in. Please ask for a new link and try again.'; }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head><body><main><section>
<h1>Little Gay Film Club™</h1><p>Use your personal link from an organiser to sign in on this device.</p>
<?php if ($error): ?><p role="alert"><?= Web::escape($error) ?></p><?php endif; ?>
<form method="post" id="login-form" hidden><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="token" id="login-token"><p>This personal link signs you in and remembers this device for 90 days.</p><button>Sign in</button></form>
<noscript>JavaScript is needed to use the personal link.</noscript>
</section></main><script src="login.js"></script></body></html>
