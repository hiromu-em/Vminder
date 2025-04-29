<?php

/**
 * パスワード・メールアドレス検証クラス
 */
class Validation
{
    public $loginErrors;

    public $DataDuplicationError;

    public $loginErrorMessage;

    public $mailValidationErrors = [];

    public $passValidationErrors = [];

    /**
     * ログイン時の検証
     */
    public function loginValidation($email, $password): array
    {
        $this->loginErrors = array_merge($this->mailValidation($email), $this->passValidation($password));
        return $this->loginErrors;
    }

    /**
     * データーベースで重複するメールアドレス
     */
    public function userDataDuplication(): string
    {
        return $this->DataDuplicationError = '既に登録されているメールアドレスです';
    }


    /**
     * メールアドレス・パスワードに差異がある
     */
    public function userDataInquiry()
    {
        return $this->loginErrorMessage = '正しいメールアドレスとパスワードを入力してください';
    }

    /**
     * メールの書式検証
     */
    public function mailValidation(string $mailAddress): array
    {

        $domain = substr(strrchr($mailAddress, '@'), 1);

        if (empty($mailAddress)) {
            $this->mailValidationErrors[] = 'メールアドレスを入力してください';

        } else if (!filter_var($mailAddress, FILTER_VALIDATE_EMAIL)) {
            $this->mailValidationErrors[] = '無効なメールアドレスです';

        } else if (!checkdnsrr($domain, 'MX')) {
            $this->mailValidationErrors[] = '無効なメールアドレスです';
        }

        return $this->mailValidationErrors;
    }

    /**
     * パスワード書式検証
     */
    public function passValidation($password)
    {

        if (empty($password)) {
            $this->passValidationErrors[] = 'パスワードを入力してください';

        } else if (strlen($password) < 8) {
            $this->passValidationErrors[] = 'パスワードは8文字以上入力してください';

        } else if (!preg_match('/^(?=.*[a-zA-Z\d])(?=.*[!@#$%^&*()\-_=+]).+$/', $password)) {
            $this->passValidationErrors[] = 'パスワードは半角英数字と記号で入力してください';

        }

        return $this->passValidationErrors;
    }
}