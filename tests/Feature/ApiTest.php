<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_login(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_search_restaurants_and_read_demo_details(): void
    {
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];

        $search = $this->withHeaders($headers)
            ->getJson('/api/restaurants?city=Jakarta')
            ->assertOk()
            ->assertJsonPath('success', true);

        $restaurantId = $search->json('data.0.id');

        $this->withHeaders($headers)
            ->getJson('/api/restaurants/'.$restaurantId.'/menu')
            ->assertOk()
            ->assertJsonPath('data.0.source', 'demo');

        $this->withHeaders($headers)
            ->getJson('/api/restaurants/'.$restaurantId.'/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.source', 'demo');
    }

    public function test_telegram_webhook_accepts_update(): void
    {
        Http::fake();

        $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret'),
        ])->postJson('/api/telegram/webhook', [
            'update_id' => 123456,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 123],
                'date' => time(),
                'text' => 'burger',
            ],
        ])->assertOk()->assertJson(['success' => true]);
    }
}
