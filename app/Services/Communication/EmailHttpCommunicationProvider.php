<?php

namespace App\Services\Communication;

final class EmailHttpCommunicationProvider extends HttpCommunicationProvider
{
    protected function channel(): string
    {
        return 'email';
    }
}
