<?php

require __DIR__ . '/../databaseConfig.php';
require __DIR__ . '/../error/ErrorMail.php';

/**
 * Vtuberのチャンネル情報を取得するクラス
 */
class Channel
{
    private $reminRegisterList;

    /**
     * リマインダーに登録されたVtuberのIDを初期化
     */
    public function __construct()
    {
        try {

            $stmt = databaseConnection()->prepare('SELECT * FROM reminder_register');
            $stmt->execute();
            $this->reminRegisterList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            exit;
        }

        empty($this->reminRegisterList) ? exit : '';
    }

    /**
     * メンバーIDからチャンネルIDを取得
     */
    public function getIdList(): array
    {
        try {
            $memberIdList = array_values(array_unique(array_column($this->reminRegisterList, 'member_id')));
            $placeholders = implode(',', array_fill(0, count($memberIdList), '?'));
            $query = "SELECT * FROM (
	            SELECT id, channel_id FROM hololive_member
	            UNION ALL
	            SELECT id, channel_id FROM nizisanzi_member
	            UNION ALL
	            SELECT id, channel_id FROM vspo_member
            ) AS merged WHERE id IN($placeholders) ORDER BY id ASC";

            $stmt = databaseConnection()->prepare($query);
            $stmt->execute($memberIdList);
            $channelIdList = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'channel_id');

        } catch (PDOException $e) {

            ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
            exit;
        } catch (Exception $e) {

            ErrorMail::send($e->getMessage(), 'getIdListFailure', 'getIdListFailure');
            exit;
        }

        return $channelIdList;
    }

    /**
     * チャンネルの最新アクティビティIDを取得
     */
    public function getActivitieId(array $channelIdList): array
    {

        $date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $publishedAfter = $date->modify('-5 minute');
        $publishedAfter = $publishedAfter->format('Y-m-d\TH:i:s\Z');
        $publishedBefore = $date->format('Y-m-d\TH:i:s\Z');

        $videoIdList = [];
        $multiHandler = curl_multi_init();
        $handles = [];

        foreach ($channelIdList as $channelId) {

            $parameter = [
                "part" => "snippet,contentDetails",
                "channelId" => $channelId,
                "publishedAfter" => $publishedAfter,
                "publishedBefore" => $publishedBefore,
                "fields" => "items(snippet(type),contentDetails(upload(videoId)))",
                "key" => $_ENV['APIKEY']
            ];

            $url = "https://www.googleapis.com/youtube/v3/activities" . "?" . http_build_query($parameter);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($multiHandler, $ch);

            $handles[] = $ch;
        }


        $active = null;
        do {
            $mrc = curl_multi_exec($multiHandler, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multiHandler) != -1) {
                do {

                    $mrc = curl_multi_exec($multiHandler, $active);

                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($handles as $ch) {

            try {
                $response = curl_multi_getcontent($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    throw new Exception("{$error}");
                }

                if ($httpcode != 200) {
                    $responseData = json_decode($response, true);
                    throw new Exception("code:{$httpcode} message:{$responseData['error']['message']}");
                }

                curl_multi_remove_handle($multiHandler, $ch);
                curl_close($ch);

            } catch (Exception $e) {

                ErrorMail::send($e->getMessage(), 'getActivitieIdFailure', 'getActivitieIdFailure');
                exit;
            }

            $memberActivity = json_decode($response, true);

            if (empty($memberActivity['items'])) {
                continue;
            }

            foreach ($memberActivity['items'] as $activity) {

                if ($activity['snippet']['type'] === 'upload') {
                    $videoIdList[] = $activity['contentDetails']['upload']['videoId'];
                }
            }
        }

        curl_multi_close($multiHandler);

        if (empty($videoIdList)) {
            exit;
        }

        return $videoIdList;
    }

    /**
     * ビデオライブの詳細を取得
     */
    public function getlVideoLiveDetail(array $videoIdList): array
    {
        $video = implode(",", $videoIdList);
        $parameter = [
            "part" => "snippet,liveStreamingDetails",
            "id" => $video,
            "fields" => "items(id,snippet(title,channelId,liveBroadcastContent),liveStreamingDetails(scheduledStartTime))",
            "key" => $_ENV['APIKEY']
        ];

        try {

            $url = "https://www.googleapis.com/youtube/v3/videos" . "?" . http_build_query($parameter);
            $initialize = curl_init();

            curl_setopt($initialize, CURLOPT_URL, $url);
            curl_setopt($initialize, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($initialize);
            $httpcode = curl_getinfo($initialize, CURLINFO_RESPONSE_CODE);

            if (curl_errno($initialize)) {
                $error = curl_error($initialize);
                throw new Exception("{$error}");
            }

            if ($httpcode != 200) {
                $responseData = json_decode($response, true);
                throw new Exception("code:{$httpcode} message:{$responseData['error']['message']}");
            }

            curl_close($initialize);
            $responseData = json_decode($response, true);

        } catch (Exception $e) {

            ErrorMail::send($e->getMessage(), 'getlVideoLiveDetailFailure', 'getlVideoLiveDetailFailure');
            exit;
        }

        $videoDetailList = [];
        $member = [];
        $query = "SELECT id AS member_id, channel_name FROM (
            SELECT id, channel_name, channel_id FROM hololive_member
            UNION ALL
            SELECT id, channel_name, channel_id FROM nizisanzi_member
            UNION ALL
            SELECT id, channel_name, channel_id FROM vspo_member
        ) AS merged WHERE channel_id = ? ORDER BY id ASC";

        foreach ($responseData['items'] as $detail) {
            if ($detail['snippet']['liveBroadcastContent'] === 'upcoming' && !empty($detail['liveStreamingDetails']['scheduledStartTime'])) {
                try {

                    $stmt = databaseConnection()->prepare($query);
                    $stmt->execute([$detail['snippet']['channelId']]);
                    $member['member'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    $detail = array_merge($detail, $member);
                    $videoDetailList[] = $detail;

                } catch (PDOException $e) {

                    ErrorMail::send($e->getTraceAsString(), 'connectionFailure', 'データーベース接続エラー');
                    exit;
                }
            }
        }

        if (empty($videoDetailList)) {
            exit;
        }

        return $videoDetailList;
    }
}