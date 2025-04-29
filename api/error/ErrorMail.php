<?php

use MonologPHPMailer\PHPMailerHandler;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../../vendor/autoload.php';

class ErrorMail
{
    /**
     * エラーメッセージ送信
     */
    public static function send(string $errorMessage, string $channel, string $subject = ''): void
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = $_ENV['GMAIL_USERNAME'];
        $mailer->Password = $_ENV['GMAIL_PASSWORD'];
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = 587;
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        
        $mailer->Subject = $subject;
        $mailer->setFrom($_ENV['GMAIL_USERNAME']);
        $mailer->addAddress($_ENV['GMAIL_USERNAME']);
        $handler = new PHPMailerHandler($mailer);
        $logger = new Logger($channel, [$handler], [], new DateTimeZone('Asia/Tokyo'));
        $logger->pushProcessor(new IntrospectionProcessor);
        $logger->pushProcessor(new MemoryUsageProcessor);
        $logger->pushProcessor(new WebProcessor);
        $handler->setFormatter(new HtmlFormatter('Y-m-d\\TH:i:s'));
        $logger->error($errorMessage);
    }
}