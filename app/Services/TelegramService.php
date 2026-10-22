<?php

namespace App\Services;

use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;

    private string $baseUrl;

    public function __construct(private RestaurantService $restaurantService)
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
            'contact' => $this->handleContact($chatId, $message['contact']),
            'photo' => $this->handlePhoto($chatId, $message['photo']),
            'video' => $this->handleVideo($chatId, $message['video']),
            default => $this->sendMessage($chatId, 'Jenis pesan belum didukung.'),
        };
    }

    private function handleText(int $chatId, string $text): void
    {
        $restaurants = $this->restaurantService->search(['q' => trim($text)]);

        if ($restaurants === []) {
            $this->sendMessage($chatId, "Restoran tidak ditemukan untuk: {$text}");

            return;
        }

        $lines = ["Hasil restoran untuk: {$text}", ''];

        foreach (array_slice($restaurants, 0, 10) as $index => $restaurant) {
            $number = $index + 1;
            $name = $restaurant['nama_restoran'] ?? $restaurant['name'] ?? 'Tanpa nama';
            $address = $restaurant['alamat'] ?? $restaurant['address'] ?? 'Alamat tidak tersedia';
            $category = $restaurant['kategori'] ?? $restaurant['category'] ?? 'Kategori tidak tersedia';
            $lines[] = "{$number}. {$name}";
            $lines[] = "   {$address}";
            $lines[] = "   Kategori: {$category}";
            $lines[] = '';
        }

        if (count($restaurants) > 10) {
            $lines[] = 'Menampilkan 10 hasil pertama.';
        }

        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleLocation(int $chatId, array $location): void
    {
        $lat = $location['latitude'] ?? 0;
        $lon = $location['longitude'] ?? 0;
        $restaurants = $this->restaurantService->search([]);
        $lines = ["Lokasi diterima: {$lat}, {$lon}", 'Restoran tersedia:'];

        foreach (array_slice($restaurants, 0, 10) as $index => $restaurant) {
            $name = $restaurant['nama_restoran'] ?? 'Tanpa nama';
            $address = $restaurant['alamat'] ?? 'Alamat tidak tersedia';
            $lines[] = ($index + 1).". {$name} - {$address}";
        }

        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleContact(int $chatId, array $contact): void
    {
        $name = $contact['first_name'] ?? 'pengguna';
        $this->sendMessage($chatId, "Kontak {$name} diterima. Data kontak hanya dicatat sebagai metadata.");
    }

    private function handlePhoto(int $chatId, array $photos): void
    {
        $this->sendMessage($chatId, 'Foto diterima. Metadata file sudah dicatat; analisis gambar belum dilakukan.');
    }

    private function handleVideo(int $chatId, array $video): void
    {
        $this->sendMessage($chatId, 'Video diterima. Metadata file sudah dicatat; analisis video belum dilakukan.');
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
