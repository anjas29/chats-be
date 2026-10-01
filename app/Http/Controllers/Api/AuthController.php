<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->tokenResponse($user, $request->input('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        abort_if($user->is_banned, 403, 'This account has been banned.');

        return $this->tokenResponse($user, $request->input('device_name'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $request = request();
        $request->setUserResolver(fn () => $user);

        return response()->json([
            'token' => $user->createToken($deviceName ?? 'mobile')->plainTextToken,
            'user' => UserResource::make($user)->resolve($request),
        ], $status);
    }
}
