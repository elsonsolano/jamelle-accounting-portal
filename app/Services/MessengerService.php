<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MessengerService
{
    private Client $client;
    private string $pageAccessToken;
    private string $graphUrl = 'https://graph.facebook.com/v21.0';

    public function __construct()
    {
        $this->client          = new Client();
        $this->pageAccessToken = config('services.messenger.page_access_token');
    }

    public function sendText(string $recipientId, string $text): void
    {
        try {
            $this->client->post("{$this->graphUrl}/me/messages", [
                'query' => ['access_token' => $this->pageAccessToken],
                'json'  => [
                    'recipient' => ['id' => $recipientId],
                    'message'   => ['text' => $text],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Messenger sendText failed', ['error' => $e->getMessage(), 'recipient' => $recipientId]);
        }
    }

    public function getSenderName(string $senderId): ?string
    {
        try {
            $response = $this->client->get("{$this->graphUrl}/{$senderId}", [
                'query' => [
                    'fields'       => 'name',
                    'access_token' => $this->pageAccessToken,
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['name'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Could not fetch FB sender name', ['sender_id' => $senderId]);
            return null;
        }
    }

    public function downloadImage(string $url): ?string
    {
        try {
            // Messenger attachment URLs are direct CDN links — no auth needed
            $response = $this->client->get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                ],
            ]);
            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            Log::error('Failed to download FB image', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
