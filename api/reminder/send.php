<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/Channel.php';
require __DIR__ . '/../dashboard/UserData.php';
require_once __DIR__ . '/../error/ErrorMail.php';
require __DIR__ . '/../../vendor/autoload.php';

$channel = new Channel();

$channelIdList = $channel->getIdList();

$activitieIdList = $channel->getActivitieId($channelIdList);

$videoLiveDetailList = $channel->getlVideoLiveDetail($activitieIdList);

try {

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->SMTPKeepAlive = true;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;
    $mail->Username = $_ENV['GMAIL_USERNAME'];
    $mail->Password = $_ENV['GMAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($_ENV['GMAIL_USERNAME'], "Vminder-VverseHub-");
    $mail->isHTML(true);

    $mail->DKIM_domain = 'gmail.com';
    $mail->DKIM_private = __DIR__ . '/../../dkim_private.pem';
    $mail->DKIM_selector = 'v-minder';
    $mail->DKIM_identity = $mail->From;
    $mail->DKIM_copyHeaderFields = false;

    $mailAddressList = UserData::getEmailAddress($videoLiveDetailList);

    foreach ($mailAddressList as $mailAddress) {

        $mail->addAddress($mailAddress);

        $query = <<<query
            SELECT reminder_register.member_id FROM users
            INNER JOIN reminder_register ON users.id = reminder_register.user_id
            WHERE users.email = :mailAddress
            query;

        $statement = databaseConnection()->prepare($query);
        $statement->bindParam(':mailAddress', $mailAddress);
        $statement->execute();
        $registerMemberId = array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'member_id');

        $videoList = [];
        $channelNameList = [];
        foreach ($videoLiveDetailList as $videoLiveDetai) {

            if (in_array($videoLiveDetai['member']['member_id'], $registerMemberId)) {

                $channelNameList[] = $videoLiveDetai['member']['channel_name'];

                $date = new DateTime($videoLiveDetai['liveStreamingDetails']['scheduledStartTime']);
                $date->setTimezone(new DateTimeZone("Asia/Tokyo"));
                $date = $date->format("Y-m-d H:i");

                $videoList[] = <<<TEXT
                <li><p>{$videoLiveDetai['snippet']['title']}</p></li>
                <li><p>開始時間：$date</p></li>
                <li><a href="https://www.youtube.com/watch?v={$videoLiveDetai['id']}">https://www.youtube.com/watch?v={$videoLiveDetai['id']}</a></li>
                <p class="section-line">--------------------------------------------------------------------</p>
                TEXT;
            }
        }

        $channelNameList = array_unique($channelNameList);
        $channelName = implode(" ", $channelNameList);
        $subject = "{$channelName}の配信が下記日程に始まります！";
        $mail->Subject = $subject;

        $mailTemplate = file_get_contents(__DIR__ . "/contents.html");
        $videoInfo = implode('', $videoList);
        $body = str_replace("{{videoList}}", $videoInfo, $mailTemplate);
        $mail->Body = $body;

        $mail->send();
        $mail->clearAddresses();
    }

    $mail->smtpClose();

} catch (Exception $message) {

    ErrorMail::send($message->errorMessage(), 'ReminderEmailFailure', 'リマインダーメール送信エラー');
    exit;
}