<?php

require_once __DIR__ . '/../dashboard/UserData.php';
require_once __DIR__ . '/../error/ErrorMail.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$predisClient = new Predis\Client($_ENV['REDIS_URL'], ['prefix' => 'user:']);
$handler = new Predis\Session\Handler($predisClient, ['gc_maxlifetime' => 86400]);
$handler->register();
session_start();

if (googleCertification()) {

    unset($_SESSION['state'], $_SESSION['codeVerifier']);
    $userDate = UserData::oauthUserRegister($_SESSION['email']);

    $_SESSION['id'] = $userDate['id'];
    $_SESSION['email'] = $userDate['email'];
    $_SESSION['password'] = $userDate['password_hash'];
    header("Location: /signup");
    exit;
} else {

    header("Location: /oauth-certification-failure");
    exit;
}

/**
 * GoogleOauth2認証
 * @throws Google\Service\Exception
 * @throws Exception
 */
function googleCertification(): bool
{

    if (isset($_SESSION['email'], $_SESSION['accessToken'], $_SESSION['tokenData'])) {
        return true;
    }

    try {
        $googleClient = new Google\Client();
        $googleClient->setClientId($_ENV['G_CLIENTID']);
        $googleClient->setClientSecret($_ENV['G_CLIENTSECRET']);
        $googleClient->setRedirectUri($_ENV['G_REDIRECT_URL']);
        $googleClient->addScope('https://www.googleapis.com/auth/userinfo.email');
        $googleClient->setAccessType('offline');
        $googleClient->setIncludeGrantedScopes(true);

        if (!isset($_GET['code'])) {

            $state = bin2hex(random_bytes(128 / 8));
            $_SESSION['state'] = $state;
            $googleClient->setState($state);

            $_SESSION['codeVerifier'] = $googleClient->getOAuth2Service()->generateCodeVerifier();
            $auth_url = $googleClient->createAuthUrl();
            header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
            exit;
        } else {

            isset($_SESSION['state']) && $_SESSION['state'] === $_GET['state'] ? '' : throw new Exception('googleStateOauthDetection');
            $accessToken = $googleClient->fetchAccessTokenWithAuthCode($_GET['code'], $_SESSION['codeVerifier']);

            if ($tokenData = $googleClient->verifyIdToken($accessToken['id_token'])) {

                $_SESSION['tokenData'] = $tokenData;
                $_SESSION['email'] = $tokenData['email'];
                $_SESSION['accessToken'] = $accessToken;

                return true;
            }
        }

        return false;

    } catch (Google\Service\Exception $e) {

        $errorMessage = implode(",", $e->getErrors());
        ErrorMail::send($errorMessage, 'googleOauthFailure', 'googleOauthの認証失敗');
        header("Location: /oauth-certification-failure");
        exit;
    } catch (Exception $e) {

        if ($e->getMessage() === 'googleStateOauthDetection') {

            ErrorMail::send($e->getMessage(), 'googleStateOauthDetection', 'googleStateOauthの差異検知');
            header("Location: /oauth-certification-failure");
            exit;
        } else {

            ErrorMail::send($e->getMessage(), 'Exception');
            header("Location: /oauth-certification-failure");
            exit;
        }
    }
}