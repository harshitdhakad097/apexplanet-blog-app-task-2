<?php
session_start();
require 'config.php';

$post_id = (int)($_POST['post_id'] ?? 0);
if ($post_id <= 0) { header('Location: index.php'); exit; }

// Increment likes_count safely
$stmt = $conn->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?");
$stmt->bind_param('i', $post_id);
$stmt->execute();
$stmt->close();

header('Location: view_post.php?id='.$post_id);
exit;
