<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/lesson-management-system/includes/db_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lesson-management-system/includes/database_functions.php';

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

// スタンプ情報を取得する際に、中間目標情報も取得
$sql = "SELECT s.*, su.start_date, su.frequency_type, su.frequency_count, su.intermediate_goal_type, su.intermediate_goal_count 
        FROM stamps s
        JOIN stamp_usage su ON s.id = su.stamp_id
        WHERE s.user_id = ? AND s.status = 'registered' 
        GROUP BY s.id
        ORDER BY su.start_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stamps = $result->fetch_all(MYSQLI_ASSOC);

$new_stamp_created = false;
$new_stamp_id = null;
if (isset($_SESSION['new_stamp_created']) && $_SESSION['new_stamp_created']) {
    $new_stamp_created = true;
    $new_stamp_id = $_SESSION['new_stamp_id'];
    unset($_SESSION['new_stamp_created']);
    unset($_SESSION['new_stamp_id']);
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタンプ使用ページ - <?php echo htmlspecialchars($user['nickname'] ?? 'ゲスト'); ?>さん</title>
    <link rel="stylesheet" href="styles.css">
    <script src="use-stamp.js" defer></script>
</head>

<body>
    <header class="main-header">
        <nav class="main-nav">
            <ul class="nav-list">
                <li class="nav-item"><a href="../index.php" class="nav-link">ホーム</a></li>
                <li class="nav-item"><a href="../stamp-creation/stamp_management.php" class="nav-link">スタンプ管理</a></li>
                <li class="nav-item"><a href="../stamp-usage/index.php" class="nav-link">今日のスタンプ</a></li>
                <li class="nav-item"><a href="../my-calendar/index.php" class="nav-link">Mycalender</a></li>
                <li class="nav-item"><a href="../registration/logout.php" class="nav-link">ログアウト</a></li>
            </ul>
        </nav>
    </header>

    <h1><?php echo htmlspecialchars($user['nickname'] ?? ''); ?>さん、今日はどれを頑張りましたか？</h1>

    <?php if ($new_stamp_created): ?>
        <div class="alert alert-success">新しいスタンプが作成されました！</div>
    <?php endif; ?>

    <div id="stamps-container">
        <?php
        $stamps = array_reverse($stamps);
        foreach ($stamps as $stamp):
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/lesson-management-system' . ($stamp['image_path'] ?? '');

            $start_date = new DateTime($stamp['start_date'] ?? 'now');
            $current_date = new DateTime();
            $days_passed = $current_date->diff($start_date)->days;

            $goal_days = 0;
            $valid_goal_types = ['week', 'month', 'year'];
            if (in_array($stamp['intermediate_goal_type'] ?? '', $valid_goal_types)) {
                switch ($stamp['intermediate_goal_type']) {
                    case 'week':
                        $goal_days = ($stamp['intermediate_goal_count'] ?? 0) * 7;
                        break;
                    case 'month':
                        $goal_days = ($stamp['intermediate_goal_count'] ?? 0) * 30; // 概算
                        break;
                    case 'year':
                        $goal_days = ($stamp['intermediate_goal_count'] ?? 0) * 365;
                        break;
                }
            } else {
                error_log("Invalid intermediate_goal_type: " . ($stamp['intermediate_goal_type'] ?? 'Not set'));
                $goal_days = 0; // デフォルト値を設定
            }

            $days_left = max(0, $goal_days - $days_passed);
            ?>
            <div class="stamp <?php echo ($stamp['id'] == $new_stamp_id) ? 'new-stamp' : ''; ?>"
                data-stamp-id="<?php echo htmlspecialchars($stamp['id'] ?? ''); ?>"
                data-encouragement-message="<?php echo htmlspecialchars($stamp['encouragement_message'] ?? ''); ?>"
                data-encouragement-image="<?php echo htmlspecialchars('/lesson-management-system' . ($stamp['encouragement_image_path'] ?? '')); ?>">
                <img src="<?php echo htmlspecialchars('/lesson-management-system' . ($stamp['image_path'] ?? '')); ?>"
                    alt="<?php echo htmlspecialchars($stamp['hobby'] ?? ''); ?>">
                <div class="stamp-info">
                    <p><?php echo htmlspecialchars($stamp['hobby'] ?? ''); ?></p>
                    <p>今度の目標達成まであと<?php echo $days_left; ?>日</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="encouragementPopup" class="popup">
        <div class="popup-content">
            <span class="close">&times;</span>
            <img id="encouragementImage" src="" alt="励ましの画像">
            <p id="encouragementMessage"></p>
        </div>
    </div>

    <script src="use-stamp.js"></script>
</body>

</html>