<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegramService) {}

    public function handle(Request $request)
    {
        $update = $request->json()->all();

        if (! $this->verifySecret($request)) {
            return response()->json(['success' => false], 401);
        }

        $this->telegramService->handleUpdate($update);

        return response()->json(['success' => true], 200);
    }

    private function verifySecret(Request $request): bool
    {
        $secret = config('services.telegram.webhook_secret');

        if (! $secret) {
            return app()->environment('local', 'testing');
        }

        $token = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return $token === $secret;
    }
}
