<?php

namespace App\Services;

use App\Contracts\RestaurantProvider;
use Illuminate\Support\Facades\Http;

class ZomatoService implements RestaurantProvider
{
    public function search(array $filters): array
    {
        if (! config('services.zomato.api_key')) {
            return $this->dummySearch($filters);
        }

        $response = Http::withHeaders([
            'X-RapidAPI-Key' => config('services.zomato.api_key'),
        ])->timeout(10)->get(config('services.zomato.base_url').'/search', $filters);

        return $response->successful() ? $response->json('restaurants', []) : [];
    }

    public function find(string $id): ?array
    {
        if (! config('services.zomato.api_key')) {
            return collect($this->dummySearch([]))->firstWhere('id', $id);
        }

        $response = Http::withHeaders([
            'X-RapidAPI-Key' => config('services.zomato.api_key'),
        ])->timeout(10)->get(config('services.zomato.base_url').'/restaurant', ['id' => $id]);

        return $response->successful() ? $response->json() : null;
    }

    public function menu(string $id): array
    {
        return [];
    }

    public function reviews(string $id): array
    {
        return [];
    }

    private function dummySearch(array $filters): array
    {
        $restaurants = [
            [
                'id' => 'dummy-1',
                'name' => 'Warung Nusantara',
                'address' => 'Jakarta',
                'rating' => 4.5,
                'category' => 'Indonesian',
            ],
            [
                'id' => 'dummy-2',
                'name' => 'Sate Makmur',
                'address' => 'Bandung',
                'rating' => 4.2,
                'category' => 'Satay',
            ],
        ];

        return collect($restaurants)->filter(function (array $restaurant) use ($filters): bool {
            $query = strtolower((string) ($filters['q'] ?? ''));
            $city = strtolower((string) ($filters['city'] ?? ''));

            return ($query === '' || str_contains(strtolower($restaurant['name']), $query))
                && ($city === '' || strtolower($restaurant['address']) === $city);
        })->values()->all();
    }
}
