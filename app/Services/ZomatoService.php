<?php

namespace App\Services;

use App\Contracts\RestaurantProvider;
use Illuminate\Support\Facades\Http;

class ZomatoService implements RestaurantProvider
{
    public function search(array $filters): array
    {
        if (! config('services.zomato.api_key')) {
            return $this->datasetSearch($filters);
        }

        $response = Http::withToken(config('services.zomato.api_key'))
            ->acceptJson()
            ->timeout(10)
            ->get(config('services.zomato.base_url').'/search', $filters);

        return $response->successful() ? $response->json('restaurants', []) : [];
    }

    public function find(string $id): ?array
    {
        if (! config('services.zomato.api_key')) {
            return collect($this->loadDataset())->firstWhere('id', $id);
        }

        $response = Http::withToken(config('services.zomato.api_key'))
            ->acceptJson()
            ->timeout(10)
            ->get(config('services.zomato.base_url').'/restaurant', ['id' => $id]);

        return $response->successful() ? $response->json() : null;
    }

    public function menu(string $id): array
    {
        $restaurant = $this->find($id);

        if (! $restaurant) {
            return [];
        }

        if (! empty($restaurant['menu']) && is_array($restaurant['menu'])) {
            return $restaurant['menu'];
        }

        $name = $restaurant['nama_restoran'] ?? 'Restoran';
        $cuisine = $restaurant['jenis_masakan'] ?? 'Indonesia';

        return [
            [
                'name' => 'Menu demo '.$name,
                'description' => 'Contoh menu '.$cuisine.' untuk keperluan pengujian API.',
                'price' => null,
                'source' => 'demo',
            ],
        ];
    }

    public function reviews(string $id): array
    {
        $restaurant = $this->find($id);

        if (! $restaurant) {
            return [];
        }

        if (! empty($restaurant['review']) && is_array($restaurant['review'])) {
            return $restaurant['review'];
        }

        return [
            [
                'author' => 'Demo User',
                'rating' => null,
                'text' => 'Contoh ulasan untuk keperluan pengujian API.',
                'source' => 'demo',
            ],
        ];
    }

    private function datasetSearch(array $filters): array
    {
        $query = strtolower((string) ($filters['q'] ?? ''));
        $city = strtolower((string) ($filters['city'] ?? ''));
        $category = strtolower((string) ($filters['category'] ?? ''));

        return collect($this->loadDataset())
            ->filter(function (array $restaurant) use ($query, $city, $category): bool {
                $name = strtolower((string) ($restaurant['nama_restoran'] ?? ''));
                $address = strtolower((string) ($restaurant['alamat'] ?? ''));
                $searchCity = strtolower((string) ($restaurant['kota_pencarian'] ?? ''));
                $restaurantCategory = strtolower((string) ($restaurant['kategori'] ?? ''));
                $cuisine = strtolower((string) ($restaurant['jenis_masakan'] ?? ''));

                return ($query === '' || str_contains($name, $query) || str_contains($cuisine, $query))
                    && ($city === '' || str_contains($searchCity, $city) || str_contains($address, $city))
                    && ($category === '' || str_contains($restaurantCategory, $category) || str_contains($cuisine, $category));
            })
            ->values()
            ->all();
    }

    private function loadDataset(): array
    {
        static $dataset;

        if ($dataset !== null) {
            return $dataset;
        }

        $path = database_path('data/restoran.json');
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = $contents === false ? [] : json_decode($contents, true);

        return $dataset = is_array($decoded['restoran'] ?? null) ? $decoded['restoran'] : [];
    }
}
