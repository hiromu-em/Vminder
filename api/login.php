<?php

use Predis\Client;
use Predis\Session\Handler;

require __DIR__ . '/databaseConfig.php';
require __DIR__ . '/Validation.php';
require __DIR__ . '/error/ErrorMail.php';
require __DIR__ . '/../vendor/autoload.php';

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
// $dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === "GET") {

    //新規登録時ユーザーデータ重複エラー表示
    if (!empty($_COOKIE['error'])) {
        $validator = new Validation();
        $loginError = $validator->userDataDuplication();
        setcookie("error", "true", time() - 3600, "/");
    }
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];
    $validator = new Validation();
    $errors = $validator->loginValidation($email, $password);

    if (empty($errors)) {
        try {

            $stmt = databaseConnection()->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($userDateRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $userDateRow['password_hash'])) {

                    $client = new Client($_ENV['REDIS_URL'], ['prefix' => 'user:']);
                    $handler = new Handler($client, ['gc_maxlifetime' => 86400]);
                    $handler->register();
                    session_start();

                    $_SESSION['id'] = $userDateRow['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['password'] = $userDateRow['password_hash'];

                    header("Location: /dashboard");
                    exit;
                } else {

                    $loginError = $validator->userDataInquiry();
                }
            }

            if (empty($userDateRow)) {
                $loginError = $validator->userDataInquiry();
            }

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン-Vminder-</title>
    <link rel="stylesheet" href="/css/login.css">
</head>

<body>
    <h1><a href="/">Vminder</a></h1>
    <form method="POST">
        <label for="email">メールアドレス：</label>
        <input type="email" id="email" name="email" placeholder="sample@example.com" required autocomplete="off">
        <label for="password">パスワード：</label>
        <input type="password" id="password" name="password" placeholder="半角英数字記号で8文字以上" required>
        <button type="submit">ログイン</button>
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($loginError)): ?>
            <div class="error-message">
                <p><?php echo $loginError; ?></p>
            </div>
        <?php endif; ?>
    </form>
</body>

</html>