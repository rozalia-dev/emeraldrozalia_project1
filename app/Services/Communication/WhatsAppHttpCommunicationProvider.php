<?php

namespace App\Services\Communication;

final class WhatsAppHttpCommunicationProvider extends HttpCommunicationProvider
{
    protected function channel(): string
    {
        return 'whatsapp';
    }
}
