<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
session_start();

function back($status) {
    header('Location: index.html?contact=' . $status . '#contact');
    exit;
}

// The host redirected the request and the form data was lost (POST turned into GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') back('lost');
if (!$_POST) back('empty');

// Honeypot: bots fill this hidden field
if (!empty($_POST['website'])) back('sent');

// Works even if the mbstring extension is missing
function cut($s, $n) { return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
function len($s) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }

$name    = trim(cut((string)($_POST['name'] ?? ''), 80));
$email   = trim(cut((string)($_POST['email'] ?? ''), 120));
$message = trim(cut((string)($_POST['message'] ?? ''), 2000));

if ($name === '') back('name');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) back('email');
if (len($message) < 5) back('short');

// Simple flood control: one message per 30 seconds per visitor
if (!empty($_SESSION['last_msg']) && time() - $_SESSION['last_msg'] < 30) back('wait');

$all = read_messages();
// Keep the file from growing forever
if (count($all) >= 500) $all = array_slice($all, -499);
$all[] = [
    'id'      => time() . mt_rand(100, 999),
    'name'    => $name,
    'email'   => $email,
    'message' => $message,
    'date'    => date('Y-m-d H:i'),
];
if (!write_messages($all)) back('fail');

$_SESSION['last_msg'] = time();

// Best-effort email (may be blocked on free hosting; message is already saved)
if (!empty($NOTIFY_EMAIL)) {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers = "From: noreply@$host\r\nReply-To: " . str_replace(["\r", "\n"], '', $email) . "\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($NOTIFY_EMAIL, 'New message from your website', "From: $name <$email>\n\n$message", $headers);
}

back('sent');
