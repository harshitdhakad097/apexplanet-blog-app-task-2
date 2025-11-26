<?php
// delete_post.php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Verify ownership
$stmt = $conn->prepare("SELECT author_id FROM posts WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($author_id);
if ($stmt->fetch() === null) {
    $stmt->close();
    header('Location: index.php');
    exit;
}
$stmt->close();

if ($author_id != $_SESSION['user_id']) {
    echo "You are not allowed to delete this post."; exit;
}

// Delete
$del = $conn->prepare("DELETE FROM posts WHERE id = ?");
$del->bind_param('i', $id);
if ($del->execute()) {
    $del->close();
    header('Location: index.php');
    exit;
} else {
    echo "Failed to delete.";
    $del->close();
}
?>
