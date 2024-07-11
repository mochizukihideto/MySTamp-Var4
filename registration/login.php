<?php
session_start();
require_once('../includes/db_connection.php');

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT id, name, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['name'];
            header("Location: ../stamp-creation/index.php");
            exit();
        } else {
            $error = "パスワードが正しくありません。";
        }
    } else {
        $error = "ユーザーが見つかりません。";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <h1>ログイン</h1>
        <?php if (!empty($error)) echo "<p class='error'>". htmlspecialchars($error) ."</p>"; ?>
        <form method="post">
            <input type="email" name="email" placeholder="メールアドレス" required>
            <input type="password" name="password" placeholder="パスワード" required>
            <button type="submit">ログイン</button>
        </form>
        <p>アカウントをお持ちでない方は<a href="index.php">こちら</a>から登録してください。</p>
    </div>
</body>
</html>