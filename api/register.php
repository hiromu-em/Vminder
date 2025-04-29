<?php

require __DIR__ . '/databaseConfig.php';
require __DIR__ . '/Validation.php';
require __DIR__ . '/certification/CertificationMail.php';
require_once __DIR__ . '/error/ErrorMail.php';

if ($_SERVER['REQUEST_METHOD'] === "POST") {

    $mailAddress = $_POST['email'];
    $validator = new Validation();
    $errors = $validator->mailValidation($mailAddress);

    if (empty($errors)) {

        //メール認証の有効期限設定
        $timeZone = new DateTimeZone('Asia/Tokyo');
        $nowTime = new DateTimeImmutable("now", $timeZone);
        $time = $nowTime->modify('+30 minutes');
        $expires = $time->format('Y/m/d H:i:s');

        try {

            $token = bin2hex(random_bytes(32));
            $stmt = databaseConnection()->prepare('INSERT INTO users_temp (email, token, expires) VALUES (?, ?, ?)');
            $stmt->execute([$mailAddress, $token, $expires]);

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }

        $certification = new CertificationMail($mailAddress, $token);
        $sendingVerify = $certification->mailSend();
        header("Location: $sendingVerify");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録-Vminder-</title>
    <link rel="stylesheet" href="/css/register.css">
</head>

<body>
    <h1><a href="/">Vminder</a></h1>
    <form method="POST">
        <label for="email">メールアドレス：</label>
        <input type="email" id="email" name="email" placeholder="sample@example.com" required autocomplete="off">
        <button type="submit">送信</button>
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
</body>

</html>