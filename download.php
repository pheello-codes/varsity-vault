<?php
$page_title = 'Download Note';
include 'includes/config.php';
include 'includes/auth_check.php';

if (!isset($_GET['note_id']) || !is_numeric($_GET['note_id'])) {
    header('Location: dashboard.php');
    exit();
}

$note_id = (int)$_GET['note_id'];
$user_id = $_SESSION['user_id'];

$note_stmt = $conn->prepare("SELECT n.id, n.title, n.file_path, n.seller_id, u.name AS seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.id = ?");
$note_stmt->bind_param('i', $note_id);
$note_stmt->execute();
$note = $note_stmt->get_result()->fetch_assoc();

if (!$note || empty($note['file_path'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'Note not available for download.';
    exit();
}

$auth_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$auth_stmt->bind_param('i', $user_id);
$auth_stmt->execute();
$user_data = $auth_stmt->get_result()->fetch_assoc();
$is_admin = !empty($user_data['is_admin']);

$authorized = false;

$purchase_stmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND note_id = ? LIMIT 1");
$purchase_stmt->bind_param('ii', $user_id, $note_id);
$purchase_stmt->execute();
if ($purchase_stmt->get_result()->num_rows > 0) {
    $authorized = true;
}

if ($note['seller_id'] === $user_id || $is_admin) {
    $authorized = true;
}

if (!$authorized) {
    header('HTTP/1.1 403 Forbidden');
    echo 'You are not authorized to download this file.';
    exit();
}

$full_path = realpath(__DIR__ . '/' . $note['file_path']);
$uploads_dir = realpath(__DIR__ . '/uploads/notes');

if (!$full_path || strpos($full_path, $uploads_dir) !== 0 || !is_file($full_path)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File could not be found.';
    exit();
}

$log_stmt = $conn->prepare("INSERT INTO download_logs (user_id, note_id) VALUES (?, ?)");
$log_stmt->bind_param('ii', $user_id, $note_id);
$log_stmt->execute();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Content-Length: ' . filesize($full_path));
header('Accept-Ranges: bytes');

readfile($full_path);
exit();
