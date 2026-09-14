<?php

namespace App\Jobs;

use App\Services\AutomationRuleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAutomationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $event,
        public array $payload,
        public string $eventKey,
        public ?int $companyId = null,
    ) {
    }

    public function handle(AutomationRuleService $automations): void
    {
        $automations->run($this->event, $this->payload, $this->eventKey, $this->companyId);
    }
}
