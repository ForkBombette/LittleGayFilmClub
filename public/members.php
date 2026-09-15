<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
use LGFC\Auth;
use LGFC\Database;
use LGFC\Members;
use LGFC\Removals;
use LGFC\Web;
Web::start(); $pdo=Database::connect(); $user=Auth::requireUser($pdo); Auth::guardOrganiser($pdo,$user);
$link=null; $error=''; $notice=$_SESSION['member_notice']??''; unset($_SESSION['member_notice']);
$target=0; $action='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    Web::checkCsrf();
    try {
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        $name=is_string($_POST['display_name']??null)?$_POST['display_name']:'';
        if ($action==='create') {
            Members::create($pdo,$user['id'],$name);
            $_SESSION['member_notice']='Member added. Create their personal link below when you are ready to invite them.';
            header('Location: members.php',true,303);exit;
        }
        $target=filter_var($_POST['target']??null,FILTER_VALIDATE_INT);
        if (!$target) throw new DomainException('Choose a member.');
        if ($action==='update') {
            $role=is_string($_POST['role']??null)?$_POST['role']:'';
            $active=$_POST['is_active']??null;
            if (!in_array($active,['1','0'],true)) throw new DomainException('Choose Active or Inactive.');
            $version=is_string($_POST['version']??null)?$_POST['version']:'';
            Members::update($pdo,$user['id'],$target,$name,$role,$active==='1',$version);
            $_SESSION['member_notice']='Member updated. Existing votes and nominations have been retained.';
            $user=Auth::requireUser($pdo);
            header('Location: '.($user['role']==='organiser'?'members.php':'account.php'),true,303);exit;
        } elseif ($action==='issue') {
            $link='login.php#'.Auth::issue($pdo,$user['id'],$target);
            $notice='Personal link created. It can be used once within seven days. Copy its address and share it privately with that member.';
        } elseif ($action==='revoke') {
            Auth::revoke($pdo,$user['id'],$target);
            $_SESSION['member_notice']='All devices and unused login links revoked for this member.';
            Auth::requireUser($pdo);
            header('Location: members.php',true,303);exit;
        } else throw new DomainException('Choose a valid action.');
    } catch (DomainException $exception) {$error=$exception->getMessage();}
    catch (Throwable $exception) {$error='Could not update this member. Please try again.';}
}
$members=$pdo->query('SELECT id,display_name,role,is_active FROM users ORDER BY is_active DESC,display_name')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Members · Little Gay Film Club™</title><link rel="stylesheet" href="styles.css"></head><body><?php LGFC\Navigation::render($user, 'members.php'); ?><main id="main-content" tabindex="-1">
<header><h1>Members</h1>
<p>Manage names, membership and organiser permissions. Keep at least one organiser active.</p><nav class="page-jumps" aria-label="On this page"><span>On this page</span><a href="#member-directory">Member directory</a><a href="#add-member">Add a member</a></nav></header>
<?php if($error || $notice): ?><section><p role="<?= $error?'alert':'status' ?>"><?= Web::escape($error?:$notice) ?></p>
<?php if($link): ?><p><a href="<?= Web::escape($link) ?>">Personal sign-in link — copy link address</a></p><?php endif; ?></section><?php endif; ?>
<section id="add-member"><h2>Add a member</h2><form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="create">
<label>Display name <input name="display_name" required maxlength="100" value="<?= Web::escape($error && $action==='create' && is_string($_POST['display_name']??null)?$_POST['display_name']:'') ?>"></label>
<p>New accounts start as active members. You can give them organiser permissions below.</p><button>Add member</button></form></section>
<section id="member-directory"><h2>Current and inactive members</h2>
<p>Inactive members cannot sign in, nominate or vote. Deactivating a member cancels their devices and unused links; reactivation needs a new link. Their existing ballots and nominations stay recorded.</p>
<p>Active membership determines removal-vote counts. The current removal threshold is <?= Removals::threshold($pdo) ?> supporters. Membership changes affect pending requests when a response is next saved; completed decisions stay unchanged.</p>
<p>Creating a link replaces any unused link for that member. Revoking devices and links signs them out without deactivating their membership.</p>
<?php foreach($members as $member):
    $values=$member; $version=Members::version($member);
    if ($error && $action==='update' && (int)$target===(int)$member['id']) {
        foreach(['display_name','role','is_active'] as $key) if(is_string($_POST[$key]??null)) $values[$key]=$_POST[$key];
        $version=is_string($_POST['version']??null)?$_POST['version']:'';
    }
?>
<details class="member-entry" <?= $error && $action==='update' && (int)$target===(int)$member['id'] ? 'open' : '' ?>><summary><?= Web::escape($member['display_name']) ?><?= (int)$member['id']===$user['id']?' · You':'' ?><span><?= $member['is_active']?'Active':'Inactive' ?> · <?= Web::escape($member['role']) ?></span></summary>
<form method="post" class="nomination-form"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="target" value="<?= (int)$member['id'] ?>"><input type="hidden" name="version" value="<?= Web::escape($version) ?>">
<label>Display name <input name="display_name" required maxlength="100" value="<?= Web::escape($values['display_name']) ?>"></label>
<label>Role <select name="role"><option value="member" <?= $values['role']==='member'?'selected':'' ?>>Member</option><option value="organiser" <?= $values['role']==='organiser'?'selected':'' ?>>Organiser</option></select></label>
<label>Membership <select name="is_active"><option value="1" <?= (int)$values['is_active']===1?'selected':'' ?>>Active</option><option value="0" <?= (int)$values['is_active']===0?'selected':'' ?>>Inactive</option></select></label>
<button>Save member</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= Web::escape($_SESSION['csrf']) ?>"><input type="hidden" name="target" value="<?= (int)$member['id'] ?>"><?php if($member['is_active']): ?><button name="action" value="issue">Create personal link</button><?php endif; ?> <button name="action" value="revoke">Revoke devices and links</button></form>
</details><?php endforeach; ?></section></main></body></html>
