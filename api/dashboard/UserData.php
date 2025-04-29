<?php

require_once __DIR__ . '/../databaseConfig.php';
require_once __DIR__ . '/../error/ErrorMail.php';
require __DIR__ . '/../Validation.php';

/**
 * ユーザー情報を操作するクラス
 */
class UserData
{
    private $id;

    private $email;

    private $password;

    public function __construct(int $id, string $email, ?string $password)
    {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
    }

    /**
     * ユーザーID取得
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * リマインダー登録
     * @param array $reminderRegister 登録したメンバーID
     */
    public function insertRegisterMembersId(array $reminderRegister): void
    {
        try {

            $query = 'INSERT INTO reminder_register (user_id, member_id) VALUES' . implode(',', $reminderRegister);
            $stmt = databaseConnection()->prepare($query);
            $stmt->execute();

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }

    }

    /**
     * リマインダー登録済みメンバーID取得
     */
    public function getRegisterMembersId(): array
    {
        try {

            $stmt = databaseConnection()->prepare('SELECT member_id FROM reminder_register WHERE user_id = ?');
            $stmt->execute([$this->getId()]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }
    }

    /**
     * リマインダー登録メンバーID解除
     * @param array 解除メンバーID
     */
    public function reminderCancellation(array $reminderCancellation = []): void
    {

        if (empty($reminderCancellation)) {

            try {

                $stmt = databaseConnection()->prepare("DELETE FROM reminder_register");
                $stmt->execute();

            } catch (PDOException $e) {

                ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
                header("Location: /connection-failure");
                exit;
            }

        } else {

            $placeholder = implode(',', array_fill(0, count($reminderCancellation), '?'));

            try {

                $stmt = databaseConnection()->prepare("DELETE FROM reminder_register WHERE member_id IN($placeholder)");
                $stmt->execute($reminderCancellation);

            } catch (PDOException $e) {

                ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
                header("Location: /connection-failure");
                exit;
            }
        }
    }

    /**
     * リマインダー登録されたメンバーIDからユーザーのメールアドレス取得
     */
    public static function getEmailAddress(array $videoLiveDetailList): array
    {
        foreach ($videoLiveDetailList as $videoLiveDetail) {
            $memberIdList[] = $videoLiveDetail['member']['member_id'];
        }

        $memberIdList = array_values(array_unique($memberIdList));
        $placeHoloId = implode(",", array_fill(0, count($memberIdList), "?"));
        $query = <<<TEXT
        SELECT users.email FROM reminder_register
        INNER JOIN users ON reminder_register.user_id = users.id
        WHERE reminder_register.member_id IN($placeHoloId);
        TEXT;

        try {

            $stmt = databaseConnection()->prepare($query);
            $stmt->execute($memberIdList);
            $mailAddressList = array_values(array_unique($stmt->fetchAll(PDO::FETCH_COLUMN)));

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }

        return $mailAddressList;
    }

    /**
     * oauth認証のユーザーデータ登録
     */
    public static function oauthUserRegister(string $email): array
    {
        try {

            $stmt = databaseConnection()->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (empty($userData)) {

                $stmt = databaseConnection()->prepare('INSERT INTO users (email) VALUES (?) RETURNING *');
                $stmt->execute([$email]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $userData;

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            header("Location: /connection-failure");
            exit;
        }
    }
}