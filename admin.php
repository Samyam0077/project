<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
session_start();
header('X-Robots-Tag: noindex, nofollow');

$ADMIN_PASSWORD = trim((string)$ADMIN_PASSWORD); // ignore accidental spaces in config.php
$configured = ($ADMIN_PASSWORD !== '' && $ADMIN_PASSWORD !== '(PASSWORD_HERE)');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$notice = '';

function slugify($s) {
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
    return $s !== '' ? $s : 'cat-' . substr(uniqid(), -5);
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'login') {
        sleep(1); // slows down password guessing
        if ($configured && hash_equals($ADMIN_PASSWORD, trim((string)($_POST['password'] ?? '')))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: admin.php');
            exit;
        }
        $notice = 'Wrong password.';
    } elseif (!empty($_SESSION['admin']) && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $data = read_questions();

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: admin.php');
            exit;
        }
        if ($action === 'add_cat') {
            $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 60));
            if ($name === '') { $notice = 'Category name is required.'; }
            else {
                $id = slugify($name);
                $ids = array_column($data['categories'], 'id');
                while (in_array($id, $ids, true)) $id .= '-' . substr(uniqid(), -3);
                $data['categories'][] = ['id' => $id, 'name' => $name, 'items' => []];
                $notice = write_questions($data) ? 'Category added.' : 'Could not save (check folder permissions).';
            }
        }
        if ($action === 'del_cat') {
            $id = (string)($_POST['cat'] ?? '');
            $data['categories'] = array_values(array_filter($data['categories'], function ($c) use ($id) { return $c['id'] !== $id; }));
            $notice = write_questions($data) ? 'Category deleted.' : 'Could not save.';
        }
        if ($action === 'add_item') {
            $id    = (string)($_POST['cat'] ?? '');
            $title = trim(mb_substr((string)($_POST['title'] ?? ''), 0, 120));
            $type  = trim(mb_substr((string)($_POST['type'] ?? ''), 0, 30));
            $url   = trim((string)($_POST['url'] ?? ''));
            if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL) || stripos($url, 'https://') !== 0) {
                $notice = 'Title and a valid https:// link are required.';
            } else {
                foreach ($data['categories'] as &$c) {
                    if ($c['id'] === $id) { $c['items'][] = ['title' => $title, 'type' => $type !== '' ? $type : 'Drive', 'url' => $url]; }
                }
                unset($c);
                $notice = write_questions($data) ? 'Link added.' : 'Could not save.';
            }
        }
        if ($action === 'del_item') {
            $id = (string)($_POST['cat'] ?? '');
            $i  = (int)($_POST['idx'] ?? -1);
            foreach ($data['categories'] as &$c) {
                if ($c['id'] === $id && isset($c['items'][$i])) { array_splice($c['items'], $i, 1); }
            }
            unset($c);
            $notice = write_questions($data) ? 'Link deleted.' : 'Could not save.';
        }
        if ($action === 'del_msg') {
            $mid = (string)($_POST['mid'] ?? '');
            $msgs = array_filter(read_messages(), function ($m) use ($mid) { return (string)$m['id'] !== $mid; });
            write_messages($msgs);
            $notice = 'Message deleted.';
        }
    } elseif ($action !== 'login') {
        $notice = 'Session expired, please log in again.';
    }
}

$logged = !empty($_SESSION['admin']);
$csrf = $_SESSION['csrf'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin | Samyam Rijal</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/stylesamyam.css">
<style>
  .wrap { max-width: 1000px; margin: 0 auto; padding: 40px 24px 90px; }
  .wrap h1 { font-size: 32px; margin-bottom: 6px; }
  .box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; margin-bottom: 22px; }
  .box h2 { font-size: 18px; margin-bottom: 14px; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; }
  .row input, .row select, .login input {
    background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 11px 14px;
    color: var(--text); font-family: inherit; font-size: 14px; flex: 1 1 160px; min-width: 0;
  }
  .notice { background: var(--accent-soft); border: 1px solid var(--accent); color: var(--text); padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; }
  .item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
  .item a { color: var(--accent); text-decoration: none; word-break: break-all; }
  .item small { color: var(--text-muted); display: block; }
  .del { background: transparent; border: 1px solid #5a2a35; color: #f87171; border-radius: 8px; padding: 6px 12px; cursor: pointer; font-family: inherit; }
  .del:hover { background: rgba(248,113,113,0.1); }
  .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 26px; }
  .login { max-width: 380px; margin: 80px auto; }
  .login input { width: 100%; margin-bottom: 12px; }
  .muted { color: var(--text-muted); font-size: 14px; }
  .msg { padding: 14px 0; border-bottom: 1px solid var(--border); }
  .msg p { white-space: pre-wrap; margin: 6px 0 10px; font-size: 14px; }
</style>
</head>
<body>
<div class="wrap">
<?php if (!$logged): ?>
  <div class="login box">
    <h1>Admin</h1>
    <p class="muted" style="margin-bottom:16px">Log in to manage past questions and messages.</p>
    <?php if (!$configured): ?>
      <div class="notice">Admin is locked. Open <b>config.php</b> and change <b>$ADMIN_PASSWORD</b> first.</div>
    <?php endif; ?>
    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <input type="password" name="password" placeholder="Password" required autofocus>
      <button class="btn btn-primary" type="submit">Log in</button>
    </form>
  </div>
<?php else:
  $data = read_questions();
  $msgs = array_reverse(read_messages());
?>
  <div class="top">
    <div><h1>Admin <span class="grad-text">panel</span></h1><p class="muted">Changes go live instantly on the Past Questions page.</p></div>
    <div class="row">
      <a class="btn btn-outline" href="past-questions.html" target="_blank">View page</a>
      <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="btn btn-outline" type="submit">Log out</button></form>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>

  <div class="box">
    <h2>Add category</h2>
    <form method="post" class="row">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_cat">
      <input name="name" placeholder="e.g. 5th Semester" maxlength="60" required>
      <button class="btn btn-primary" type="submit">Add</button>
    </form>
  </div>

  <?php if ($data['categories']): ?>
  <div class="box">
    <h2>Add past question link</h2>
    <form method="post" class="row">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_item">
      <select name="cat" required>
        <?php foreach ($data['categories'] as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <input name="title" placeholder="Title (e.g. Operating Systems 2080)" maxlength="120" required>
      <input name="type" placeholder="Type (e.g. Board Exam)" maxlength="30">
      <input name="url" type="url" placeholder="https://drive.google.com/..." required style="flex-basis:100%">
      <button class="btn btn-primary" type="submit">Add link</button>
    </form>
  </div>
  <?php endif; ?>

  <?php foreach ($data['categories'] as $c): ?>
  <div class="box">
    <div class="top" style="margin-bottom:6px">
      <h2 style="margin:0"><?= e($c['name']) ?> <span class="muted">(<?= count($c['items']) ?>)</span></h2>
      <form method="post" onsubmit="return confirm('Delete this whole category?');">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="del_cat"><input type="hidden" name="cat" value="<?= e($c['id']) ?>">
        <button class="del" type="submit">Delete category</button>
      </form>
    </div>
    <?php if (!$c['items']): ?><p class="muted">No links yet.</p><?php endif; ?>
    <?php foreach ($c['items'] as $i => $it): ?>
      <div class="item">
        <div><?= e($it['title']) ?> <span class="pq-tag"><?= e($it['type'] ?? '') ?></span>
          <small><a href="<?= e($it['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($it['url']) ?></a></small></div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="del_item">
          <input type="hidden" name="cat" value="<?= e($c['id']) ?>"><input type="hidden" name="idx" value="<?= (int)$i ?>">
          <button class="del" type="submit">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <div class="box">
    <h2>Messages (<?= count($msgs) ?>)</h2>
    <?php if (!$msgs): ?><p class="muted">No messages yet.</p><?php endif; ?>
    <?php foreach ($msgs as $m): ?>
      <div class="msg">
        <b><?= e($m['name']) ?></b> &lt;<a href="mailto:<?= e($m['email']) ?>" style="color:var(--accent)"><?= e($m['email']) ?></a>&gt;
        <span class="muted"> &middot; <?= e($m['date']) ?></span>
        <p><?= e($m['message']) ?></p>
        <form method="post" onsubmit="return confirm('Delete this message?');">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="del_msg"><input type="hidden" name="mid" value="<?= e($m['id']) ?>">
          <button class="del" type="submit">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
