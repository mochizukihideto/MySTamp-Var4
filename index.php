<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db_connection.php';
require_once '../includes/database_functions.php';

// デバッグ情報
error_log("Session data: " . print_r($_SESSION, true));
error_log("User ID: " . ($_SESSION['user_id'] ?? 'Not set'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log("Session not active");
}

// ユーザーがログインしていない場合、ログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header("Location: ../registration/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id);
if (!$user) {
    error_log("User not found for ID: $user_id");
    // エラー処理（例：エラーページへのリダイレクト）
    header("Location: ../error.php");
    exit();
}

$stamps = get_registered_user_stamps($conn, $user_id);
if ($stamps === false) {
    error_log("Failed to get registered stamps for user ID: $user_id");
    // エラー処理
    $stamps = []; // 空の配列を設定して続行
}


?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタンプ使用ページ - <?php echo htmlspecialchars($user['name']); ?>さん</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <h1><?php echo htmlspecialchars($user['name']); ?>さんのスタンプ使用ページ</h1>

    <div id="stamps-container">
        <?php foreach ($stamps as $stamp): ?>
            <div class="stamp">
                <img src="/lesson-management-system<?php echo htmlspecialchars($stamp['image_path']); ?>"
                    alt="<?php echo htmlspecialchars($stamp['hobby']); ?>">
                <p><?php echo htmlspecialchars($stamp['hobby']); ?></p>
                <button class="use-stamp" data-stamp-id="<?php echo $stamp['id']; ?>">使用</button>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const useStampButtons = document.querySelectorAll('.use-stamp');

            useStampButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const stampId = this.dataset.stampId;
                    useStamp(stampId);
                });
            });
        });

        function useStamp(stampId) {
            fetch('../api/use_stamp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ stamp_id: stampId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('スタンプを使用しました！');
                        // 必要に応じて、UI更新のロジックをここに追加
                    } else {
                        alert('エラーが発生しました: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('エラーが発生しました。');
                });
        }
    </script>
</body>

</html>