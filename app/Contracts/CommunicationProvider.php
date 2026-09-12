<?php

namespace App\Contracts;

use App\Models\ConversationMessage;
use App\Support\CommunicationSendResult;

interface CommunicationProvider
{
    public function send(ConversationMessage $message): CommunicationSendResult;
}
