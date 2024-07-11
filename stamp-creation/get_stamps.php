<?php
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'ユーザーがログインしていません。']);
    exit;
}

$user_id = $_SESSION['user_id'];

// get_stamps.php の変更部分
$sql = "SELECT id, hobby, CONCAT('/lesson-management-system', image_path) AS image_path, status FROM stamps WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$stamps = [];
while ($row = $result->fetch_assoc()) {
    $stamps[] = $row;
}

echo json_encode($stamps);


