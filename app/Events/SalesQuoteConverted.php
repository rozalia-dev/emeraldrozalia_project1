<?php

namespace App\Events;

use App\Models\Order;
use App\Models\SalesQuote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SalesQuoteConverted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SalesQuote $quote,
        public readonly Order $order,
        public readonly string $correlationId,
    ) {
    }
}
