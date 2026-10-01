<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAvatarRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return UserResource::make($request->user());
    }

    public function avatar(UpdateAvatarRequest $request): UserResource
    {
        $user = $request->user();
        $disk = config('chats.media_disk');
        $previous = $user->avatar_path;

        $path = $request->file('avatar')->storeAs('avatars', Str::ulid().'.'.$request->file('avatar')->extension(), $disk);
        $user->update(['avatar_path' => $path]);

        if ($previous) {
            Storage::disk($disk)->delete($previous);
        }

        return UserResource::make($user);
    }
}
