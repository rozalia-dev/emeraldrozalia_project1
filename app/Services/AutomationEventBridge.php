<?php

namespace App\Services;

use App\Events\{ApprovalRequestChanged, CommunicationConversationChanged, CommunicationTemplateChanged, SalesQuoteConverted};

final class AutomationEventBridge
{
    public function salesQuoteConverted(SalesQuoteConverted $event): void
    {
        $companyId = $event->order->company_id ?: $event->quote->company_id;
        app(AutomationRuleService::class)->queue('quote.converted', [
            'company_id' => $companyId,
            'quote_uuid' => $event->quote->uuid,
            'order_uuid' => $event->order->public_uuid,
            'title' => 'Sales quote converted',
            'source' => 'Sales Quote',
        ], 'quote:'.$event->quote->uuid, $companyId ? (int) $companyId : null);
    }

    public function conversationChanged(CommunicationConversationChanged $event): void
    {
        $action = in_array($event->action, ['created', 'updated'], true) ? $event->action : 'updated';
        $companyId = $event->conversation->company_id;
        app(AutomationRuleService::class)->queue('communication.conversation.'.$action, [
            'company_id' => $companyId,
            'conversation_uuid' => $event->conversation->uuid,
            'title' => $event->conversation->subject ?: 'Communication conversation updated',
            'source' => 'Communication Center',
        ], 'conversation:'.$event->conversation->uuid.':'.$action, $companyId ? (int) $companyId : null);
    }

    public function approvalChanged(ApprovalRequestChanged $event): void
    {
        $companyId = $event->approval->company_id;
        app(AutomationRuleService::class)->queue('approval.changed', [
            'company_id' => $companyId,
            'approval_uuid' => $event->approval->uuid,
            'title' => $event->approval->title,
            'source' => 'Approval Center',
            'action' => $event->action,
        ], 'approval:'.$event->approval->uuid.':'.$event->action, $companyId ? (int) $companyId : null);
    }

    public function templateChanged(CommunicationTemplateChanged $event): void
    {
        $companyId = $event->template->company_id;
        app(AutomationRuleService::class)->queue('communication.template.updated', [
            'company_id' => $companyId,
            'template_uuid' => $event->template->uuid,
            'title' => $event->template->name,
            'source' => 'Communication Template',
            'action' => $event->action,
        ], 'template:'.$event->template->uuid.':'.$event->action, $companyId ? (int) $companyId : null);
    }
}
