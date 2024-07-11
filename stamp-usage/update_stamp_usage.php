<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/database_functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ユーザーがログインしていません']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['stamp_id'])) {
    echo json_encode(['success' => false, 'message' => 'スタンプIDが指定されていません']);
    exit();
}

$user_id = $_SESSION['user_id'];
$stamp_id = $input['stamp_id'];
$today = date("Y-m-d");

$result = update_stamp_usage($conn, $user_id, $stamp_id, $today);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => 'スタンプの使用が記録されました']);
} elseif ($result === 'no_update') {
    echo json_encode(['success' => true, 'message' => 'スタンプの使用頻度条件を満たしていないため、更新されませんでした']);
} else {
    echo json_encode(['success' => false, 'message' => 'スタンプの更新中にエラーが発生しました']);
}