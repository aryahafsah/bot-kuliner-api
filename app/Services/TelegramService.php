<?php

namespace App\Services;

use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;

    private string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->baseUrl = 'https://api.telegram.org/bot'.$this->botToken;
    }

    public function handleUpdate(array $update): void
    {
        $updateId = $update['update_id'] ?? null;
        $message = $update['message'] ?? null;

        if (! $updateId || ! $message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $messageType = $this->detectMessageType($message);

        TelegramMessage::create([
            'telegram_update_id' => $updateId,
            'chat_id' => $chatId,
            'message_type' => $messageType,
            'message_metadata' => $this->sanitizeMetadata($message),
        ]);

        $this->processMessage($chatId, $message, $messageType);
    }

    private function detectMessageType(array $message): string
    {
        return match (true) {
            isset($message['text']) => 'text',
            isset($message['location']) => 'location',
            isset($message['contact']) => 'contact',
            isset($message['photo']) => 'photo',
            isset($message['video']) => 'video',
            default => 'unknown',
        };
    }

    private function sanitizeMetadata(array $message): array
    {
        return [
            'message_id' => $message['message_id'] ?? null,
            'date' => $message['date'] ?? null,
            'text' => $message['text'] ?? null,
            'location' => isset($message['location']) ? [
                'latitude' => $message['location']['latitude'] ?? null,
                'longitude' => $message['location']['longitude'] ?? null,
            ] : null,
            'photo_count' => isset($message['photo']) ? count($message['photo']) : null,
            'video_file_id' => $message['video']['file_id'] ?? null,
        ];
    }

    private function processMessage(int $chatId, array $message, string $messageType): void
    {
        match ($messageType) {
            'text' => $this->handleText($chatId, $message['text']),
            'location' => $this->handleLocation($chatId, $message['location']),
            default => $this->sendMessage($chatId, 'Message type received: '.$messageType),
        };
    }

    private function handleText(int $chatId, string $text): void
    {
        $response = "You searched: {$text}. Restaurant search feature available via API.";
        $this->sendMessage($chatId, $response);
    }

    private function handleLocation(int $chatId, array $location): void
    {
        $lat = $location['latitude'] ?? 0;
        $lon = $location['longitude'] ?? 0;
        $response = "Location received: {$lat}, {$lon}. Nearby restaurant search available via API.";
        $this->sendMessage($chatId, $response);
    }

    public function sendMessage(int $chatId, string $text): void
    {
        try {
            Http::timeout(10)->post($this->baseUrl.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }
}
