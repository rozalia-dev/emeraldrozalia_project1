<?php

namespace App\Events;

use App\Models\Approval;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ApprovalRequestChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Approval $approval,
        public readonly string $action,
        public readonly string $correlationId,
    ) {
    }
}
