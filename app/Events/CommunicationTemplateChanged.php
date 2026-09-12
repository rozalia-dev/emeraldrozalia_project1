<?php

namespace App\Events;

use App\Models\CommunicationTemplate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommunicationTemplateChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CommunicationTemplate $template,
        public readonly string $action,
        public readonly string $correlationId,
    ) {
    }
}
