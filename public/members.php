<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Web;
Web::start(); $pdo=Database::connect(); $user=Auth::requireUser($pdo); Auth::guardOrganiser($pdo,$user);
$link=null; $notice='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    Web::checkCsrf();
    try {
        $target=filter_var($_POST['target'] ?? null,FILTER_VALIDATE_INT);
        if (!$target) throw new DomainException('Choose a member.');
        if (($_POST['action'] ?? '')==='issue') { $link='login.php#'.Auth::issue($pdo,$user['id'],$target); $notice='Personal link created. It can be used once within seven days. Copy its address and share it privately with that member.'; }
        elseif (($_POST['action'] ?? '')==='revoke') { Auth::revoke($pdo,$user['id'],$target); $notice='All devices and unused login links revoked for this member.'; }
        else throw new DomainException('Choose a valid action.');
    } catch (DomainException $error) { $notice=$error->getMessage(); }
    catch (Throwable $error) { $notice='Could not update access. Please try again.'; }
}
$members=$pdo->query('SELECT id,display_name,role,is_active FROM users ORDER BY display_name')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Member login links</title><link rel="stylesheet" href="styles.css"></head><body><main><section><h1>Member login links</h1><a href="account.php">Your account</a>
<p role="status"><?= Web::escape($notice) ?></p><?php if ($link): ?><p><a href="<?= Web::escape($link) ?>">Personal sign-in link — copy link address</a></p><?php endif; ?>
<p>Creating a link replaces any unused link for that member. Revoking access signs out all their devices and cancels unused links. It does not delete their votes.</p>
<?php foreach ($members as $member): ?><article class="pool-movie"><h2><?= Web::escape($member['display_name']) ?></h2><p><?= Web::escape($member['role']) ?></p><form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="target" value="<?= (int)$member['id'] ?>"><?php if ($member['is_active']): ?><button name="action" value="issue">Create personal link</button><?php endif; ?> <button name="action" value="revoke">Revoke devices and links</button></form></article><?php endforeach; ?>
</section></main></body></html>
