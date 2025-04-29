<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../error/ErrorMail.php';
require __DIR__ . '/../../vendor/autoload.php';

/**
 * 認証メールクラス
 */
class CertificationMail
{
    private $mailAddress;

    private $token;

    public function __construct(string $mailAddress, string $token)
    {
        $this->mailAddress = $mailAddress;
        $this->token = $token;
    }

    /**
     * 認証メール送信
     * @throws \PHPMailer\PHPMailer\Exception
     * @return string /sending-verify パスワード設定URL
     */
    public function mailSend(): string
    {
        try {
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['GMAIL_USERNAME'];
            $mail->Password = $_ENV['GMAIL_PASSWORD'];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->isHTML(true);

            $mail->DKIM_domain = 'gmail.com';
            $mail->DKIM_private = __DIR__ . '/../../dkim_private.pem';
            $mail->DKIM_selector = 'v-minder';
            $mail->DKIM_identity = $mail->From;
            $mail->DKIM_copyHeaderFields = false;

            $mail->setFrom($_ENV['GMAIL_USERNAME'], 'Vminder-VverseHub-');
            $mail->addAddress($this->mailAddress);
            $mail->Subject = '[重要]ご本人様確認のお願い';
            $mailTemp = file_get_contents(__DIR__ . '/verify-mail.html');
            $verify_url = "https://vminde.vercel.app///authenticating?token={$this->token}";
            $mailTemp = str_replace('{{url}}', $verify_url, $mailTemp);
            $mail->Body = $mailTemp;
            if (!$mail->send()) {
                throw new Exception($mail->ErrorInfo);
            }

            return '/sending-verify';

        } catch (Exception $message) {

            ErrorMail::send($message->errorMessage(), 'sendingFailure', '認証メール送信エラー');
            header('Location: /sending-failure');
            exit;
        }
    }
}