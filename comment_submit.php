<?php
// comment_submit.php
session_start();
require 'config.php';

$post_id = (int)($_POST['post_id'] ?? 0);
if ($post_id <= 0) { header('Location: index.php'); exit; }

$user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$name = trim($_POST['name'] ?? ($_SESSION['username'] ?? 'Guest'));
$content = trim($_POST['content'] ?? '');

if ($content === '') {
    $_SESSION['comment_error'] = "Comment cannot be empty.";
    header('Location: view_post.php?id='.$post_id);
    exit;
}

$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, name, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param('iiss', $post_id, $user_id, $name, $content);
$stmt->execute();
$stmt->close();

header('Location: view_post.php?id='.$post_id);
exit;
