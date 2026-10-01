<?php

namespace App\Http\Controllers\Api;

use App\Actions\MarkAsRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkAsReadRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationReadController extends Controller
{
    public function __invoke(MarkAsReadRequest $request, Conversation $conversation, MarkAsRead $markAsRead): JsonResponse
    {
        $this->authorize('view', $conversation);

        $markAsRead->handle($conversation, $request->user(), $request->integer('message_id'));

        return response()->json(status: 204);
    }
}
