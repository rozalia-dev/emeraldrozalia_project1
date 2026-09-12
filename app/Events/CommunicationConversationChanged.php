<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommunicationConversationChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $action,
        public readonly string $correlationId,
    ) {
    }
}
