<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RestaurantService;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function __construct(private RestaurantService $restaurantService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        $restaurants = $this->restaurantService->search($validated);

        return response()->json([
            'success' => true,
            'message' => 'Restaurant data retrieved successfully',
            'data' => $restaurants,
        ], 200);
    }

    public function show(string $id)
    {
        $restaurant = $this->restaurantService->find($id);

        if (! $restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Restaurant detail retrieved successfully',
            'data' => $restaurant,
        ], 200);
    }

    public function menu(string $id)
    {
        $menu = $this->restaurantService->menu($id);

        return response()->json([
            'success' => true,
            'message' => 'Menu retrieved successfully',
            'data' => $menu,
        ], 200);
    }

    public function reviews(string $id)
    {
        $reviews = $this->restaurantService->reviews($id);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews,
        ], 200);
    }
}
