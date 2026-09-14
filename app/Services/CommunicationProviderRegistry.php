<?php

namespace App\Services;

use App\Contracts\CommunicationProvider;
use RuntimeException;

class CommunicationProviderRegistry
{
    public function for(string $channel): ?CommunicationProvider
    {
        abort_unless(in_array($channel, ['email', 'whatsapp', 'chat'], true), 404);

        $class = config('communication.channels.'.$channel);
        if (! is_string($class) || trim($class) === '') {
            if (! filled(config('communication.endpoints.'.$channel))) {
                return null;
            }

            $class = config('communication.default_providers.'.$channel);
        }

        if (! is_string($class) || trim($class) === '') {
            return null;
        }

        if (! class_exists($class) || ! is_a($class, CommunicationProvider::class, true)) {
            throw new RuntimeException('The configured communication provider is invalid.');
        }

        return app($class);
    }
}
