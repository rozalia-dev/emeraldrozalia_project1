<?php

namespace App\Services\Communication;

use App\Contracts\CommunicationProvider;
use App\Models\ConversationMessage;
use App\Support\CommunicationSendResult;

final class BrowserChatCommunicationProvider implements CommunicationProvider
{
    public function send(ConversationMessage $message): CommunicationSendResult
    {
        return new CommunicationSendResult(
            status: 'delivered',
            providerMessageId: 'browser-chat-'.$message->uuid,
        );
    }
}
