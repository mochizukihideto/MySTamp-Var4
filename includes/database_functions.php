<?php
// includes/database_functions.php

// データベース接続を確保
require_once 'db_connection.php';

/**
 * ユーザーIDからユーザー情報を取得する関数
 * @param mysqli $conn データベース接続オブジェクト
 * @param int $user_id ユーザーID
 * @return array|null ユーザー情報の連想配列、見つからない場合はnull
 */
function get_user_by_id($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * スタンプを削除する関数
 * @param int $stamp_id 削除するスタンプのID
 * @return bool 削除が成功したかどうか
 */
function deleteStamp($stamp_id) {
    global $conn;
    
    // トランザクション開始
    $conn->begin_transaction();

    try {
        // データベースからスタンプ情報を取得
        $sql = "SELECT image_path FROM stamps WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $stamp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stamp = $result->fetch_assoc();

        // データベースからスタンプを削除
        $sql = "DELETE FROM stamps WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $stamp_id);
        $stmt->execute();

        // 対応する画像ファイルを削除
        if ($stamp && file_exists($_SERVER['DOCUMENT_ROOT'] . $stamp['image_path'])) {
            if (!unlink($_SERVER['DOCUMENT_ROOT'] . $stamp['image_path'])) {
                throw new Exception("画像ファイルの削除に失敗しました。");
            }
        }

        // トランザクションをコミット
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // エラーが発生した場合、トランザクションをロールバック
        $conn->rollback();
        error_log("スタンプ削除エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * ユーザーのスタンプを取得する関数
 * @param mysqli $conn データベース接続オブジェクト
 * @param int $user_id ユーザーID
 * @return array ユーザーのスタンプ情報の配列
 */
function get_user_stamps($conn, $user_id) {
    $sql = "SELECT * FROM stamps WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * スタンプ使用を更新または挿入する関数
 * @param mysqli $conn データベース接続オブジェクト
 * @param int $user_id ユーザーID
 * @param int $stamp_id スタンプID
 * @param string $date 使用日
 * @return mixed 操作の結果
 */
function update_stamp_usage($conn, $user_id, $stamp_id, $date) {
    // 最新のスタンプ使用情報を取得
    $check_sql = "SELECT * FROM stamp_usage WHERE user_id = ? AND stamp_id = ? ORDER BY start_date DESC LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $stamp_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_usage = $result->fetch_assoc();

    if ($existing_usage) {
        // 頻度条件をチェック
        $should_update = check_frequency($existing_usage, $date);
        
        if ($should_update) {
            // intermediate_goal_typeのバリデーション
            $valid_goal_types = ['week', 'month', 'year'];
            $intermediate_goal_type = $existing_usage['intermediate_goal_type'];
            if (!in_array($intermediate_goal_type, $valid_goal_types)) {
                error_log("Invalid intermediate_goal_type: " . $intermediate_goal_type);
                return false;
            }

            // 新しい使用記録を挿入
            $insert_sql = "INSERT INTO stamp_usage (user_id, stamp_id, start_date, frequency_type, frequency_count, duration, intermediate_goal_type, intermediate_goal_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissiisi", 
                $user_id, 
                $stamp_id, 
                $date, 
                $existing_usage['frequency_type'], 
                $existing_usage['frequency_count'], 
                $existing_usage['duration'], 
                $intermediate_goal_type, 
                $existing_usage['intermediate_goal_count']
            );
            $result = $insert_stmt->execute();
            if (!$result) {
                error_log("Error inserting stamp usage: " . $insert_stmt->error);
            }
            return $result;
        } else {
            // 頻度条件を満たしていない場合
            return 'no_update';
        }
    } else {
        // 使用情報がない場合（エラー）
        error_log("No existing usage found for user_id: $user_id, stamp_id: $stamp_id");
        return false;
    }
}

/**
 * 頻度条件をチェックする関数
 * @param array $usage 現在の使用情報
 * @param string $current_date 現在の日付
 * @return bool 頻度条件を満たしているかどうか
 */
function check_frequency($usage, $current_date) {
    $last_use_date = new DateTime($usage['start_date']);
    $current_date = new DateTime($current_date);
    $interval = $last_use_date->diff($current_date);

    switch ($usage['frequency_type']) {
        case 'daily':
            return $interval->days >= $usage['frequency_count'];
        case 'weekly':
            return $interval->days >= ($usage['frequency_count'] * 7);
        case 'monthly':
            return $interval->m >= $usage['frequency_count'] || $interval->y > 0;
        default:
            return false;
    }
}

/**
 * 登録済みのユーザースタンプを取得する関数
 * @param mysqli $conn データベース接続オブジェクト
 * @param int $user_id ユーザーID
 * @return array 登録済みスタンプの配列
 */
function get_registered_user_stamps($conn, $user_id) {
    $sql = "SELECT * FROM stamps WHERE user_id = ? AND status = 'registered' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stamps = [];
    while ($row = $result->fetch_assoc()) {
        $stamps[] = $row;
    }
    
    return $stamps;
}

// 必要に応じて、他の関数をここに追加してください

?>