<?php

namespace App\Contracts;

interface RestaurantProvider
{
    public function search(array $filters): array;

    public function find(string $id): ?array;

    public function menu(string $id): array;

    public function reviews(string $id): array;
}
