<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MuteConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationMuteController extends Controller
{
    /**
     * Mute until the given time, or unmute when muted_until is null.
     */
    public function __invoke(MuteConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['muted_until' => $request->date('muted_until')]);

        return response()->json(['muted_until' => $request->date('muted_until')]);
    }
}
