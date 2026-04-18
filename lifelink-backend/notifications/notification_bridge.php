<?php
/**
 * LifeLink — Notification Bridge  v3
 *
 * FIXED in v3:
 *   - Removed typed class properties (private static string $X)
 *     → now compatible with PHP 7.0+ (not just 7.4+)
 *   - All method signatures kept intact
 */

class LifeLinkNotify
{
    // ── Config — no type hints on properties so PHP 7.0–7.3 works fine ───────
    private static $SERVER_URL ='http://127.0.0.1:3001';
    private static $SECRET     = '4976ed55834723f37f2090e5bf21e7fb861a3eb03e734217341fd81f228f4582';

    private static function secret()
    {
        if (empty(self::$SECRET)) {
            self::$SECRET = getenv('INTERNAL_SECRET') ?: '';
        }
        return self::$SECRET;
    }

    // ── Core HTTP POST ────────────────────────────────────────────────────────
    private static function post($endpoint, array $body)
    {
        $url  = self::$SERVER_URL . $endpoint;
        $json = json_encode($body);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json),
                'X-LifeLink-Secret: ' . self::secret(),
            ],
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[LifeLinkNotify] cURL error on {$endpoint}: {$error}");
            return ['success' => false, 'data' => null, 'http_code' => 0];
        }

        $data = json_decode($response, true);
        return [
            'success'   => ($http_code >= 200 && $http_code < 300),
            'data'      => $data,
            'http_code' => $http_code,
        ];
    }

    // ── Request accepted ──────────────────────────────────────────────────────
    public static function requestAccepted($requesterUid, $donorName, $bloodGroup, $requestId, $requesterFcmToken = null)
    {
        $result = self::post('/notify/request-accepted', [
            'requesterUid'  => $requesterUid,
            'donorName'     => $donorName,
            'bloodGroup'    => $bloodGroup,
            'requestId'     => $requestId,
            'fcmToken' => $requesterFcmToken,
        ]);
        return $result['success'];
    }

    // ── Request declined ──────────────────────────────────────────────────────
    public static function requestDeclined($requesterUid, $donorName, $bloodGroup, $requestId, $fcmToken = null)
    {
        $result = self::post('/notify/request-declined', [
            'requesterUid' => $requesterUid,
            'donorName'    => $donorName,
            'bloodGroup'   => $bloodGroup,
            'requestId'    => $requestId,
            'fcmToken'     => $fcmToken,
        ]);
        return $result['success'];
    }

    // ── New request nearby ────────────────────────────────────────────────────
    public static function newRequestNearby(array $donors, $bloodGroup, $location, $requesterName, $requestId)
    {
        $result = self::post('/notify/new-request-nearby', [
            'donors'        => $donors,
            'bloodGroup'    => $bloodGroup,
            'location'      => $location,
            'requesterName' => $requesterName,
            'requestId'     => $requestId,
        ]);
        return $result['success'];
    }

    // ── Donation complete ─────────────────────────────────────────────────────
    public static function donationComplete($donorUid, $requesterName, $points = 100, $fcmToken = null)
    {
        $result = self::post('/notify/donation-complete', [
            'donorUid'      => $donorUid,
            'requesterName' => $requesterName,
            'points'        => $points,
            'fcmToken'      => $fcmToken,
        ]);
        return $result['success'];
    }

    // ── Generic broadcast ─────────────────────────────────────────────────────
    public static function broadcast($title, $body, array $data = [])
    {
        $result = self::post('/notify/emergency-broadcast', [
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ]);
        return $result['success'];
    }

    // ── Generic send ──────────────────────────────────────────────────────────
    public static function send($toUid, $type, $title, $body, array $data = [], $fcmToken = null)
    {
        $result = self::post('/notify', [
            'toUid'    => $toUid,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'data'     => $data,
            'fcmToken' => $fcmToken,
        ]);
        return $result['success'];
    }

    // ── New campaign broadcast ────────────────────────────────────────────────
    public static function newCampaign($campaign_id, $campaign_name, $organized_by, $blood_group, $venue, $status = 'Upcoming', array $fcm_tokens = [])
    {
        $result = self::post('/notify/new-campaign', [
            'campaignId'   => $campaign_id,
            'campaignName' => $campaign_name,
            'organizedBy'  => $organized_by,
            'bloodGroup'   => $blood_group,
            'venue'        => $venue,
            'status'       => $status,
            'fcmTokens'    => $fcm_tokens,
        ]);

        if (!$result['success']) {
            error_log("[LifeLinkNotify] newCampaign failed campaign_id=$campaign_id HTTP={$result['http_code']}");
        }
        return $result['success'];
    }

    // ── Emergency alert broadcast ─────────────────────────────────────────────
    public static function emergencyAlert($alert_id, $blood_group, $location, $description = '', array $donors = [], array $all_fcm_tokens = [])
    {
        $result = self::post('/notify/emergency-alert', [
            'alertId'       => $alert_id,
            'bloodGroup'    => $blood_group,
            'location'      => $location,
            'description'   => $description,
            'donors'        => $donors,
            'allFcmTokens'  => $all_fcm_tokens,
        ]);

        if (!$result['success']) {
            error_log("[LifeLinkNotify] emergencyAlert failed alert_id=$alert_id HTTP={$result['http_code']}");
        }
        return $result['success'];
    }
}