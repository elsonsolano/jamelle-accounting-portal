<?php

namespace App\Services;

use GuzzleHttp\Client;

class GmailService
{
    private ?string $accessToken = null;
    private Client $http;

    public function __construct()
    {
        // On Windows/WAMP, use local CA bundle; on Linux (Railway), use system default
        $caBundle   = 'C:/wamp64/cacert.pem';
        $verifyOption = file_exists($caBundle) ? $caBundle : true;

        $this->http = new Client([
            'verify' => $verifyOption,
        ]);
    }

    private function getAccessToken(): string
    {
        if (!$this->accessToken) {
            $this->accessToken = $this->refreshAccessToken();
        }

        return $this->accessToken;
    }

    // -------------------------------------------------------------------------
    // OAuth helpers
    // -------------------------------------------------------------------------

    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/gmail.readonly',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/auth?' . $params;
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->http->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'code'          => $code,
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri'  => config('services.google.redirect_uri'),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function refreshAccessToken(): string
    {
        $response = $this->http->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => config('services.google.refresh_token'),
                'grant_type'    => 'refresh_token',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['access_token'];
    }

    // -------------------------------------------------------------------------
    // Gmail API calls
    // -------------------------------------------------------------------------

    /**
     * Fetch unread PayMaya settlement emails.
     * Returns array of ['message_id', 'subject', 'attachment_content']
     */
    public function fetchSettlementEmails(): array
    {
        $sender   = config('services.google.paymaya_sender', 'noreply.settlement@maya.ph');
        $today    = now()->format('Y/m/d');
        $tomorrow = now()->addDay()->format('Y/m/d');
        $query    = "from:{$sender} subject:\"SETTLEMENT BREAKDOWN\" after:{$today} before:{$tomorrow}";

        $listResponse = $this->apiGetPublic('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
            'q'          => $query,
            'maxResults' => 20,
        ]);

        $messages = $listResponse['messages'] ?? [];
        $results  = [];

        foreach ($messages as $msg) {
            $messageId = $msg['id'];
            $full      = $this->apiGetPublic("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}", [
                'format' => 'full',
            ]);

            $subject    = $this->getHeader($full['payload']['headers'] ?? [], 'Subject');
            $attachment = $this->getAttachment($messageId, $full);

            if (!$attachment) continue;

            $results[] = [
                'message_id'         => $messageId,
                'subject'            => $subject,
                'attachment_content' => $attachment,
            ];
        }

        return $results;
    }

    private function getHeader(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            if (strtolower($header['name']) === strtolower($name)) {
                return $header['value'];
            }
        }
        return '';
    }

    private function getAttachment(string $messageId, array $message): ?string
    {
        $parts = $message['payload']['parts'] ?? [];

        foreach ($parts as $part) {
            $filename = $part['filename'] ?? '';
            if ($filename && str_ends_with(strtoupper($filename), '.XLS')) {
                $attachmentId = $part['body']['attachmentId'] ?? null;

                if ($attachmentId) {
                    $att  = $this->apiGetPublic("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}");
                    $data = $att['data'] ?? '';
                    return base64_decode(strtr($data, '-_', '+/'));
                }
            }
        }

        return null;
    }

    public function apiGetPublic(string $url, array $query = []): array
    {
        $response = $this->http->get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            'query'   => $query,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
