<?php

namespace App\Services;

use App\Contracts\RestaurantProvider;

class RestaurantService
{
    public function __construct(private RestaurantProvider $provider) {}

    public function search(array $filters): array
    {
        return $this->provider->search($filters);
    }

    public function find(string $id): ?array
    {
        return $this->provider->find($id);
    }

    public function menu(string $id): array
    {
        return $this->provider->menu($id);
    }

    public function reviews(string $id): array
    {
        return $this->provider->reviews($id);
    }
}
