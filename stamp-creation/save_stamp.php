<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/error_log.txt'); // エラーログのパスを適切に設定してください

session_start();
require_once '../includes/db_connection.php';

function send_json_response($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../registration/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $hobby = filter_input(INPUT_POST, 'hobby', FILTER_SANITIZE_STRING);
        $shape = filter_input(INPUT_POST, 'shape', FILTER_SANITIZE_STRING);
        $font = filter_input(INPUT_POST, 'font', FILTER_SANITIZE_STRING);

        if (!$hobby || !$shape || !$font) {
            send_json_response(false, '必要な情報が不足しています。');
        }

        $filename = 'stamp_' . time() . '_' . uniqid() . '.png';
        $image_path = "/assets/images/generated_stamps/" . $filename;
        $full_path = $_SERVER['DOCUMENT_ROOT'] . "/lesson-management-system" . $image_path;

        $shape_path = $_SERVER['DOCUMENT_ROOT'] . "/lesson-management-system/assets/images/stamp_shapes/{$shape}.png";
        if (!file_exists($shape_path)) {
            send_json_response(false, 'シェイプファイルが見つかりません。');
        }
        $image = imagecreatefrompng($shape_path);

        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $text_color = imagecolorallocate($image, 0, 0, 0);

        $font_filename = str_replace(' ', '', $font) . ".ttf";
        $font_path = dirname(__FILE__) . "/../assets/fonts/" . $font_filename;

        $japanese_font_path = dirname(__FILE__) . "/../assets/fonts/NotoSansJP.ttf";

        if (!file_exists($font_path) || preg_match('/[\x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $hobby)) {
            $font_path = $japanese_font_path;
        }

        if (!file_exists($font_path)) {
            send_json_response(false, 'フォントファイルが見つかりません。');
        }

        $hobby = mb_convert_encoding($hobby, 'UTF-8', 'auto');
        $hobby = preg_replace('/[\x00-\x1F\x7F]/u', '', $hobby);

        $padding = (int) ($width * 0.1);
        $available_width = $width - (2 * $padding);
        $available_height = $height - (2 * $padding);

        $font_size = min($available_width, $available_height) / 5;

        do {
            $bbox = imagettfbbox($font_size, 0, $font_path, $hobby);
            if ($bbox === false) {
                send_json_response(false, 'フォントの読み込みに失敗しました。');
            }
            $text_width = $bbox[2] - $bbox[0];
            $text_height = $bbox[1] - $bbox[7];
            if ($text_width > $available_width || $text_height > $available_height) {
                $font_size *= 0.9;
            }
        } while (($text_width > $available_width || $text_height > $available_height) && $font_size > 1);

        $x = (int) (($width - $text_width) / 2);
        $y = (int) (($height - $text_height) / 2 + $text_height);

        $result = imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $hobby);
        if ($result === false) {
            send_json_response(false, 'テキストの描画に失敗しました。');
        }

        if (!imagepng($image, $full_path)) {
            send_json_response(false, 'スタンプの保存に失敗しました。');
        }
        imagedestroy($image);

        $sql = "INSERT INTO stamps (user_id, hobby, shape, font, image_path, status) VALUES (?, ?, ?, ?, ?, 'draft')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $user_id, $hobby, $shape, $font, $image_path);

        if (!$stmt->execute()) {
            send_json_response(false, 'データベースへの保存に失敗しました。');
        }

        $new_stamp_id = $stmt->insert_id;

        $_SESSION['new_stamp_created'] = true;
        $_SESSION['new_stamp_id'] = $new_stamp_id;

        header("Location: index.php");
        exit();
    } else {
        header("Location: index.php");
        exit();
    }
} catch (Exception $e) {
    error_log('Error in save_stamp.php: ' . $e->getMessage());
    header("Location: index.php?error=1");
    exit();
}