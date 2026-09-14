<?php

namespace App\Events;

use App\Models\FranchiseStore;
use App\Models\FranchiseStoreAction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FranchiseStoreLifecycleChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FranchiseStore $store,
        public readonly FranchiseStoreAction $action,
    ) {
    }
}
