<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
error_log("save_stamp_usage.php started");

require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID: " . $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log('Raw POST data: ' . $input);
    $data = json_decode($input, true);
    
    if ($data === null) {
        error_log('Failed to parse JSON data');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }

    error_log('Decoded data: ' . print_r($data, true));

    $required_fields = ['stamp_id', 'startDate', 'frequencyType', 'frequencyCount', 'duration', 'intermediateGoalType', 'intermediateGoalCount'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            error_log("Missing required field: $field");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }

    $stamp_id = $data['stamp_id'];
    $start_date = $data['startDate'];
    $frequency_type = $data['frequencyType'];
    $frequency_count = $data['frequencyCount'];
    $duration = $data['duration'];
    $intermediate_goal_type = $data['intermediateGoalType'];
    $intermediate_goal_count = $data['intermediateGoalCount'];

    $conn->begin_transaction();

    try {
        // スタンプのステータスを 'registered' に更新
        $update_sql = "UPDATE stamps SET status = 'registered' WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $stamp_id, $user_id);
        $update_stmt->execute();

        // stamp_usage テーブルにデータを挿入
        $insert_sql = "INSERT INTO stamp_usage (user_id, stamp_id, start_date, frequency_type, frequency_count, duration, intermediate_goal_type, intermediate_goal_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iississi", $user_id, $stamp_id, $start_date, $frequency_type, $frequency_count, $duration, $intermediate_goal_type, $intermediate_goal_count);
        $insert_stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Stamp registered and usage data saved successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to register stamp and save usage data']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}