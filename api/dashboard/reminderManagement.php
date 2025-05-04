<?php

use Predis\Client;
use Predis\Session\Handler;

require __DIR__ . '/UserData.php';
require_once __DIR__ . '/../error/ErrorMail.php';
require_once __DIR__ . '/../databaseConfig.php';
require_once __DIR__ . '/../../vendor/autoload.php';

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true
]);

// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
// $dotenv->load();

$client = new Client($_ENV['REDIS_URL'], ['prefix' => 'user:']);
$handler = new Handler($client, ['gc_maxlifetime' => 86400]);
$handler->register();
session_start();

if (!isset($_SESSION['id'], $_SESSION['email'])) {
    header("Location: /");
    exit;
}

try {

    $query = "SELECT * FROM (
	    SELECT * FROM hololive_member
	    UNION ALL
	    SELECT * FROM nizisanzi_member
	    UNION ALL
	    SELECT * FROM vspo_member
    ) AS merged ORDER BY id ASC";

    $stmt = databaseConnection()->prepare($query);
    $stmt->execute();
    $allMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hololiveMembers = array_filter($allMembers, function ($member) {
        return $member['member_group'] == 'ホロライブ';
    });

    $holostarsMembers = array_filter($allMembers, function ($member) {
        return $member['member_group'] == 'ホロスターズ';
    });

    $nizisanziMembers = array_filter($allMembers, function ($member) {
        return $member['member_group'] == 'にじさんじ';
    });

    $vspoMembers = array_filter($allMembers, function ($member) {
        return $member['member_group'] == 'ぶいすぽっ！';
    });

} catch (PDOException $e) {

    ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
    header("Location: /connection-failure");
    exit;
}

//登録済みメンバーIDを取得
$userDate = new UserData($_SESSION['id'], $_SESSION['email'], $_SESSION['password']);
$registeredMembers = $userDate->getRegisterMembersId();
$_SESSION['registeredMembers'] = $registeredMembers;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_SESSION['registeredMembers']) && empty($_POST['selected_members'])) {
        $error = 'メンバーを選択してください';
    }

    if (empty($_POST['selected_members']) && !empty($_SESSION['registeredMembers'])) {
        //登録済みメンバーIDのリマインダー全解除
        $userDate->reminderCancellation();
        $registeredMembers = [];
    }

    if (!empty($_POST['selected_members'])) {

        //登録済みメンバーIDのリマインダー解除
        $reminderCancellation = array_values(array_diff($_SESSION['registeredMembers'], $_POST['selected_members']));
        if (!empty($reminderCancellation)) {
            $userDate->reminderCancellation($reminderCancellation);
        }

        //登録済みで重複するメンバーIDは除外
        $duplicatesId = array_intersect($_POST['selected_members'], $_SESSION['registeredMembers']);
        $selected_members = array_diff($_POST['selected_members'], $duplicatesId);

        if (!empty($selected_members)) {

            $reminderRegister = [];
            foreach ($selected_members as $member) {
                $reminderRegister[] = "({$_SESSION['id']}, $member)";
            }

            $userDate->insertRegisterMembersId($reminderRegister);
            $registrationComplete = '登録しました。';
        }

        $registeredMembers = $userDate->getRegisterMembersId();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード-Vminder-</title>
    <link rel="stylesheet" href="/css/reminder-management.css">
    <script type="module" src="/js/reminderManagement.js"></script>
    <script src="https://unpkg.com/wanakana" defer></script>
</head>

<body>
    <header id="header">
        <h1><a href="/dashboard">Vminder</a></h1>
        <form action="/logout" method="get">
            <button type="submit" class="logout-button">ログアウト</button>
        </form>
    </header>
    <div class="main-content-wrapper">
        <?php if (!empty($error)): ?>
            <div class="error-message-container">
                <div class="error-message">
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($registrationComplete)): ?>
            <div class="registration-complete-container">
                <div class="complete-message">
                    <p><?php echo $registrationComplete; ?></p>
                </div>
            </div>
        <?php endif; ?>
            <div class="top-bak-container">
                <button class="top-button">TOPへ戻る</button>
            </div>
            <div class="group-container">
                <button class="group-select-button">
                    <h4 class="group-hololive">ホロライブ</h4>
                    <h4 class="group-holostars">ホロスターズ</h4>
                </button>
                <button class="group-select-button">
                    <h4 class="group-nizisanzi">にじさんじ</h4>
                </button>
                <button class="group-select-button">
                    <h4 class="group-vspo">ぶいすぽっ！</h4>
                </button>
            </div>
            <div class="member-search">
                <input type="search" id="member-search-box" placeholder="メンバー検索" autocomplete="off">
            </div>
            <form class="reminder-form-container" id="reminder-select"
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="dashboard-container">
                    <h3 class="group-title">ホロライブ</h3>
                    <?php foreach ($hololiveMembers as $hololiveMember): ?>
                        <label class="member-card hololive">
                            <?php
                            $isChecked = in_array($hololiveMember['id'], $registeredMembers);
                            ?>
                            <span class="group-name"><?php echo $hololiveMember['member_group']; ?></span>
                            <input type="checkbox" name="selected_members[]" value="<?php echo $hololiveMember['id']; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>>
                            <div class="img-container">
                                <img src="<?php echo $hololiveMember['img_url']; ?> "
                                    alt="<?php echo $hololiveMember['channel_name']; ?>" draggable="false">
                            </div>
                            <span class="member-name-hololive"data-kana="<?php echo $hololiveMember['channel_name_kana']; ?>">
                                <?php echo $hololiveMember['channel_name']; ?>
                            </span>
                            <span class="member-name-kana">
                                <?php echo $hololiveMember['channel_name_kana']; ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                    <h3 class="group-title">ホロスターズ</h3>
                    <?php foreach ($holostarsMembers as $holostarsMember): ?>
                        <label class="member-card holostars">
                            <?php
                            $isChecked = in_array($holostarsMember['id'], $registeredMembers);
                            ?>
                            <span class="group-name"><?php echo $holostarsMember['member_group']; ?></span>
                            <input type="checkbox" name="selected_members[]" value="<?php echo $holostarsMember['id']; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>>
                            <div class="img-container">
                                <img src="<?php echo $holostarsMember['img_url']; ?> "
                                    alt="<?php echo $holostarsMember['channel_name']; ?>" draggable="false">
                            </div>
                            <span class="member-name-holostars"data-kana="<?php echo $holostarsMember['channel_name_kana']; ?>">
                                <?php echo $holostarsMember['channel_name']; ?>
                            </span>
                            <span class="member-name-kana">
                                <?php echo $holostarsMember['channel_name_kana']; ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
                <div class="dashboard-container">
                    <h3 class="group-title">にじさんじ</h3>
                    <?php foreach ($nizisanziMembers as $nizisanziMember): ?>
                        <label class="member-card nizisanzi">
                            <?php
                            $isChecked = in_array($nizisanziMember['id'], $registeredMembers);
                            ?>
                            <span class="group-name"><?php echo $nizisanziMember['member_group']; ?></span>
                            <input type="checkbox" name="selected_members[]" value="<?php echo $nizisanziMember['id']; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>>
                            <div class="img-container">
                                <img src="<?php echo $nizisanziMember['img_url']; ?> "
                                    alt="<?php echo $nizisanziMember['channel_name']; ?>" draggable="false">
                            </div>
                            <span class="member-name-nizisanzi"data-kana="<?php echo $nizisanziMember['channel_name_kana']; ?>">
                                <?php echo $nizisanziMember['channel_name']; ?>
                            </span>
                            <span class="member-name-kana">
                                <?php echo $nizisanziMember['channel_name_kana']; ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
                <div class="dashboard-container">
                    <h3 class="group-title">ぶいすぽっ！</h3>
                    <?php foreach ($vspoMembers as $vspoMember): ?>
                        <label class="member-card vspo">
                            <?php
                            $isChecked = in_array($vspoMember['id'], $registeredMembers);
                            ?>
                            <span class="group-name"><?php echo $vspoMember['member_group']; ?></span>
                            <input type="checkbox" name="selected_members[]" value="<?php echo $vspoMember['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                            <div class="img-container">
                                <img src="<?php echo $vspoMember['img_url']; ?> "
                                    alt="<?php echo $vspoMember['channel_name']; ?>" draggable="false">
                            </div>
                            <span class="member-name-vspo"data-kana="<?php echo $vspoMember['channel_name_kana']; ?>">
                                <?php echo $vspoMember['channel_name']; ?>
                            </span>
                            <span class="member-name-kana">
                                <?php echo $vspoMember['channel_name_kana']; ?>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
            </form>
            <div class="sidebar-container">
                <div class="sidebar">
                    <ul id="sidebar-list"></ul>
                </div>
                <div class="submit-button-container">
                    <button type="submit" form="reminder-select">登録</button>
                </div>
                <div class="reset-button-container">
                    <button type="button" id="reset-all-button" form="reminder-select">全て解除</button>
                </div>
            </div>
        </div>
</body>

</html>