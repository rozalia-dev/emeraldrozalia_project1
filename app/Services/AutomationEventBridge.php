<?php

namespace App\Services;

use App\Events\{ApprovalRequestChanged, CommunicationConversationChanged, CommunicationTemplateChanged, FranchiseStoreLifecycleChanged, SalesQuoteConverted};

final class AutomationEventBridge
{
    public function salesQuoteConverted(SalesQuoteConverted $event): void
    {
        $companyId = $event->order->company_id ?: $event->quote->company_id;
        app(AutomationRuleService::class)->queue('quote.converted', [
            'company_id' => $companyId,
            'quote_uuid' => $event->quote->uuid,
            'order_id' => $event->order->getKey(),
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
            'conversation_id' => $event->conversation->getKey(),
            'conversation_uuid' => $event->conversation->uuid,
            'order_id' => $event->conversation->order_id,
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

    public function franchiseStoreChanged(FranchiseStoreLifecycleChanged $event): void
    {
        $store = $event->store;
        $action = $event->action;
        $companyId = $store->company_id;

        app(AutomationRuleService::class)->queue('franchise.store.status.changed', [
            'company_id' => $companyId,
            'franchise_store_uuid' => $store->uuid,
            'entity_uuid' => $store->uuid,
            'title' => 'Franchise store '.$action->to_status,
            'source' => 'Franchise Store Lifecycle',
            'action' => $action->action,
            'from_status' => $action->from_status,
            'to_status' => $action->to_status,
        ], 'franchise-store:'.$store->uuid.':'.$action->uuid, $companyId ? (int) $companyId : null);
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
