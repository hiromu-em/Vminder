<?php

use Predis\Client;
use Predis\Session\Handler;

require __DIR__ . '/databaseConfig.php';
require __DIR__ . '/Validation.php';
require __DIR__ . '/error/ErrorMail.php';
require __DIR__ . '/../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    try {

        $token = $_GET['token'];
        $stmt = databaseConnection()->prepare('SELECT * FROM users_temp WHERE token = ?');
        $stmt->execute([$token]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        //トークンの有無確認
        if (empty($record)) {
            header("Location: /authentication-failure");
            exit;
        }

    } catch (PDOException $e) {

        ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
        header("Location: /connection-failure");
        exit;
    }

    $stmt = databaseConnection()->prepare('DELETE FROM users_temp WHERE email = ? AND token = ?');
    $stmt->execute([$record['email'], $record['token']]);

    $timeZone = new DateTimeZone('Asia/Tokyo');
    $limitTime = new DateTime($record['expires'], $timeZone);
    $nowTime = new DateTime('now', $timeZone);

    //30分以内ならパスワード登録へ進む
    if ($nowTime >= $limitTime) {
        header('Location: /timeover');
        exit;
    }

    $_SESSION['email'] = $record['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_SESSION['email'];
    $password = $_POST['password'];
    $validator = new Validation();
    $errors = $validator->passValidation($password);

    if (empty($errors)) {
        try {

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users(email, password_hash) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = ?)";
            $stmt = databaseConnection()->prepare($query);
            $stmt->execute([$email, $passwordHash, $email]);

            //新規レコード or 既存レコード判定
            if ($stmt->rowCount() > 0) {

                $stmt = databaseConnection()->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $userId = $stmt->fetch(PDO::FETCH_ASSOC);

                $client = new Client($_ENV['REDIS_URL'], ['prefix' => 'user:']);
                $handler = new Handler($client, ['gc_maxlifetime' => 86400]);
                $handler->register();
                session_start();

                $_SESSION['email'] = $email;
                $_SESSION['password'] = $passwordHash;
                $_SESSION['id'] = $userId['id'];

                header('Location: /signup');
                exit;
            } else {

                setcookie("error", 'true', time() + 3600, "/");
                header('Location: /register-error');
                exit;
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
    <title>パスワード設定-Vminder-</title>
    <link rel="stylesheet" href="/css/set-password.css">
</head>

<body>
    <div class="container">
        <p>メール認証完了！</p>
        <form method="post">
            <label for="password">パスワード：</label>
            <input type="password" id="password" name="password" placeholder="半角英数字記号で8文字以上" required autocomplete="off">
            <button type="submit">登録</button>
        </form>
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>