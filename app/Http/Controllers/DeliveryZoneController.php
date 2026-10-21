<?php
namespace App\Http\Controllers;

use App\Services\DeliveryZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function lookup(
        Request $request,
        DeliveryZoneService $deliveryZoneService
    ): JsonResponse {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:2000'],
        ]);

        $zone = $deliveryZoneService->findZone(
            $validated['address']
        );

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery zone could not be identified. Please check the address.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'zone' => $zone,
        ]);
    }
}