<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Register or refresh an FCM token. A token always belongs to the latest user who registered it.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $device = DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->input('platform'),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['id' => $device->id], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return response()->json(status: 204);
    }
}
