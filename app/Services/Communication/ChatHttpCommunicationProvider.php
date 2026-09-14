<?php

namespace App\Services\Communication;

final class ChatHttpCommunicationProvider extends HttpCommunicationProvider
{
    protected function channel(): string
    {
        return 'chat';
    }
}
