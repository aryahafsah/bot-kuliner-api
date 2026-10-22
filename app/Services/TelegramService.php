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
        $callbackQuery = $update['callback_query'] ?? null;

        if ($callbackQuery) {
            $this->handleCallbackQuery($updateId, $callbackQuery);

            return;
        }

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

    private function handleCallbackQuery(?int $updateId, array $callbackQuery): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';

        if ($updateId && $chatId) {
            TelegramMessage::firstOrCreate(
                ['telegram_update_id' => $updateId],
                [
                    'chat_id' => $chatId,
                    'message_type' => 'callback_query',
                    'message_metadata' => [
                        'message_id' => $callbackQuery['message']['message_id'] ?? null,
                        'callback_data' => $data,
                    ],
                ],
            );
        }

        $this->answerCallbackQuery($callbackQuery['id'] ?? '');

        [$action, $restaurantId] = array_pad(explode(':', $data, 2), 2, null);

        if (! $chatId || ! $restaurantId) {
            return;
        }

        $restaurant = $this->restaurantService->find($restaurantId);

        if (! $restaurant) {
            $this->sendMessage($chatId, 'Data restoran tidak ditemukan.');

            return;
        }

        match ($action) {
            'menu' => $this->sendMenu($chatId, $restaurantId, $restaurant),
            'reviews' => $this->sendReviews($chatId, $restaurantId, $restaurant),
            'location' => $this->sendRestaurantLocation($chatId, $restaurant),
            default => $this->sendMessage($chatId, 'Pilihan tidak dikenali.'),
        };
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

        $results = array_slice($restaurants, 0, 5);
        $lines = ["Hasil restoran untuk: {$text}", ''];
        $keyboard = [];

        foreach ($results as $index => $restaurant) {
            $number = $index + 1;
            $id = (string) ($restaurant['id'] ?? '');
            $name = $restaurant['nama_restoran'] ?? $restaurant['name'] ?? 'Tanpa nama';
            $address = $restaurant['alamat'] ?? $restaurant['address'] ?? 'Alamat belum tersedia';
            $category = $restaurant['kategori'] ?? $restaurant['category'] ?? 'Kategori tidak tersedia';
            $lines[] = "{$number}. {$name}";
            $lines[] = "   {$address}";
            $lines[] = "   Kategori: {$category}";
            $lines[] = '';

            if ($id !== '') {
                $keyboard[] = [
                    ['text' => "Menu {$number}", 'callback_data' => "menu:{$id}"],
                    ['text' => "Review {$number}", 'callback_data' => "reviews:{$id}"],
                    ['text' => "Lokasi {$number}", 'callback_data' => "location:{$id}"],
                ];
            }
        }

        if (count($restaurants) > count($results)) {
            $lines[] = 'Menampilkan 5 hasil pertama. Pilih tombol untuk menu, review, atau lokasi.';
        } else {
            $lines[] = 'Pilih tombol untuk melihat menu, review, atau lokasi.';
        }

        $this->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }

    private function handleLocation(int $chatId, array $location): void
    {
        $lat = (float) ($location['latitude'] ?? 0);
        $lon = (float) ($location['longitude'] ?? 0);
        $restaurants = $this->restaurantService->search([]);

        $nearby = collect($restaurants)
            ->filter(fn (array $r) => isset($r['latitude'], $r['longitude']))
            ->map(function (array $r) use ($lat, $lon) {
                $rLat = (float) $r['latitude'];
                $rLon = (float) $r['longitude'];
                $distance = hypot($rLat - $lat, $rLon - $lon);
                $r['_dist'] = $distance;

                return $r;
            })
            ->sortBy('_dist')
            ->values()
            ->take(3)
            ->all();

        if (empty($nearby)) {
            $this->sendMessage($chatId, "Lokasi diterima ({$lat}, {$lon}), tetapi belum ada restoran terdekat di dataset.");

            return;
        }

        $top = $nearby[0];
        $topName = $top['nama_restoran'] ?? 'Restoran Terdekat';
        $this->sendMessage($chatId, "Lokasi kamu diterima! Restoran terdekat: {$topName}. Mengirimkan pin lokasi restoran:");
        $this->sendLocation($chatId, (float) $top['latitude'], (float) $top['longitude']);

        $lines = ['Daftar restoran sekitar:'];
        $keyboard = [];

        foreach ($nearby as $idx => $r) {
            $num = $idx + 1;
            $name = $r['nama_restoran'] ?? 'Restoran';
            $cat = $r['kategori'] ?? '-';
            $lines[] = "{$num}. {$name} ({$cat})";
            $id = (string) ($r['id'] ?? '');

            if ($id !== '') {
                $keyboard[] = [
                    ['text' => "Menu {$num}", 'callback_data' => "menu:{$id}"],
                    ['text' => "Review {$num}", 'callback_data' => "reviews:{$id}"],
                    ['text' => "Pin {$num}", 'callback_data' => "location:{$id}"],
                ];
            }
        }

        $this->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }

    private function handleContact(int $chatId, array $contact): void
    {
        $name = $contact['first_name'] ?? 'pengguna';
        $phone = $contact['phone_number'] ?? '-';
        $this->sendMessage($chatId, "Kontak {$name} ({$phone}) berhasil diterima dan dicatat.");
    }

    private function handlePhoto(int $chatId, array $photos): void
    {
        $this->sendMessage($chatId, 'Foto berhasil diterima! Rekomendasi kuliner visual siap dicatat.');
    }

    private function handleVideo(int $chatId, array $video): void
    {
        $this->sendMessage($chatId, 'Video berhasil diterima! Review kuliner berbasis video siap dicatat.');
    }

    private function sendMenu(int $chatId, string $restaurantId, array $restaurant): void
    {
        $name = $restaurant['nama_restoran'] ?? 'Restoran';
        $menu = $this->restaurantService->menu($restaurantId);

        $lines = ["Daftar Menu {$name}:", ''];

        if (empty($menu)) {
            $lines[] = 'Belum ada daftar menu.';
        } else {
            foreach ($menu as $idx => $item) {
                $itemName = $item['name'] ?? $item['nama'] ?? 'Menu';
                $price = ! empty($item['price']) ? ' - Rp '.number_format((float) $item['price'], 0, ',', '.') : '';
                $desc = ! empty($item['description']) ? "\n   {$item['description']}" : '';
                $lines[] = ($idx + 1).". {$itemName}{$price}{$desc}";
            }
        }

        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function sendReviews(int $chatId, string $restaurantId, array $restaurant): void
    {
        $name = $restaurant['nama_restoran'] ?? 'Restoran';
        $reviews = $this->restaurantService->reviews($restaurantId);

        $lines = ["Ulasan Pelanggan {$name}:", ''];

        if (empty($reviews)) {
            $lines[] = 'Belum ada ulasan.';
        } else {
            foreach ($reviews as $idx => $rev) {
                $author = $rev['author'] ?? $rev['user'] ?? 'Pelanggan';
                $rating = isset($rev['rating']) ? " [★ {$rev['rating']}]" : '';
                $text = $rev['text'] ?? $rev['comment'] ?? '-';
                $lines[] = ($idx + 1).". {$author}{$rating}: \"{$text}\"";
            }
        }

        $this->sendMessage($chatId, implode("\n", $lines));
    }

    private function sendRestaurantLocation(int $chatId, array $restaurant): void
    {
        $name = $restaurant['nama_restoran'] ?? 'Restoran';
        $lat = $restaurant['latitude'] ?? null;
        $lon = $restaurant['longitude'] ?? null;

        if ($lat === null || $lon === null) {
            $this->sendMessage($chatId, "Koordinat peta untuk {$name} belum tersedia.");

            return;
        }

        $this->sendMessage($chatId, "Mengirim pin lokasi {$name}:");
        $this->sendLocation($chatId, (float) $lat, (float) $lon);
    }

    public function sendLocation(int $chatId, float $latitude, float $longitude): void
    {
        try {
            Http::timeout(10)->post($this->baseUrl.'/sendLocation', [
                'chat_id' => $chatId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendLocation failed', ['error' => $e->getMessage()]);
        }
    }

    private function answerCallbackQuery(string $callbackQueryId): void
    {
        if ($callbackQueryId === '') {
            return;
        }

        try {
            Http::timeout(5)->post($this->baseUrl.'/answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram answerCallbackQuery failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendMessage(int $chatId, string $text, array $extra = []): void
    {
        try {
            $payload = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $extra);

            Http::timeout(10)->post($this->baseUrl.'/sendMessage', $payload);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }
}
