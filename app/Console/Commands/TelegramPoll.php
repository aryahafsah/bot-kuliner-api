<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--timeout=25 : Long polling timeout in seconds}';

    protected $description = 'Receive Telegram updates via long polling without a public webhook';

    public function handle(TelegramService $telegramService): int
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN belum diatur di .env');

            return self::FAILURE;
        }

        $baseUrl = 'https://api.telegram.org/bot'.$token;

        $this->info('Menghapus webhook dan update lama...');
        Http::timeout(15)->post($baseUrl.'/deleteWebhook', ['drop_pending_updates' => true]);

        $this->info('Polling dimulai. Tekan Ctrl+C untuk berhenti.');
        $offset = null;

        while (true) {
            $payload = ['timeout' => max(0, (int) $this->option('timeout'))];

            if ($offset !== null) {
                $payload['offset'] = $offset;
            }

            try {
                $response = Http::timeout($payload['timeout'] + 15)->post($baseUrl.'/getUpdates', $payload);
            } catch (\Throwable $e) {
                $this->warn('getUpdates gagal: '.$e->getMessage());
                sleep(3);

                continue;
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $this->warn('Telegram error: '.($data['description'] ?? 'tidak diketahui'));
                sleep(3);

                continue;
            }

            foreach ($data['result'] ?? [] as $update) {
                $offset = ($update['update_id'] ?? 0) + 1;

                try {
                    $telegramService->handleUpdate($update);
                } catch (\Throwable $e) {
                    Log::error('Telegram poll handling failed', ['error' => $e->getMessage()]);
                    $this->warn('Gagal memproses update: '.$e->getMessage());
                }
            }
        }
    }
}
