<?php
include 'includes/config.php';
include 'includes/auth_check.php';

if (!isset($_POST['note_id']) || !is_numeric($_POST['note_id'])) {
    header("Location: index.php");
    exit();
}

$note_id = (int)$_POST['note_id'];
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment']);

// Validate rating
if ($rating < 1 || $rating > 5) {
    header("Location: product.php?id=$note_id&error=invalid_rating");
    exit();
}

// Check if user has purchased this note
$purchase_stmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND note_id = ?");
$purchase_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
$purchase_stmt->execute();

if ($purchase_stmt->get_result()->num_rows == 0) {
    header("Location: product.php?id=$note_id&error=not_purchased");
    exit();
}

// Check if user already reviewed this note
$review_check_stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND note_id = ?");
$review_check_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
$review_check_stmt->execute();

if ($review_check_stmt->get_result()->num_rows > 0) {
    header("Location: product.php?id=$note_id&error=already_reviewed");
    exit();
}

// Insert review
$insert_stmt = $conn->prepare("INSERT INTO reviews (user_id, note_id, rating, comment) VALUES (?, ?, ?, ?)");
$insert_stmt->bind_param("iiis", $_SESSION['user_id'], $note_id, $rating, $comment);

if ($insert_stmt->execute()) {
    header("Location: product.php?id=$note_id&success=review_added");
} else {
    header("Location: product.php?id=$note_id&error=review_failed");
}
exit();
?>