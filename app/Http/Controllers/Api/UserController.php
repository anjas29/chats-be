<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(SearchUsersRequest $request): AnonymousResourceCollection
    {
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->input('search'));

        $users = User::query()
            ->where('is_banned', false)
            ->whereKeyNot($request->user()->id)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "{$term}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate(20);

        return UserResource::collection($users);
    }
}
