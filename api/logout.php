<?php

use Predis\Client;
use Predis\Session\Handler;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client($_ENV['REDIS_URL'], ['prefix' => 'user:']);
$handler = new Handler($client);
$handler->register();
session_start();

session_unset();
$handler->destroy(session_id());
$parameter = session_get_cookie_params();
setcookie(session_name(), '', time() - 86400, '/');
header("Location: /");
exit;