<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use Illuminate\Support\Collection;

final class CommunicationTemplateCatalogService
{
    public function __construct(private readonly CommunicationTemplateService $templates)
    {
    }

    public function ensureForCurrentCompany(): void
    {
        $companyId = (int) session('company_id', 0);
        if ($companyId <= 0) {
            return;
        }

        $existingKeys = CommunicationTemplate::query()
            ->get(['id', 'variables'])
            ->map(fn (CommunicationTemplate $template) => data_get($template->variables, '_system_key'))
            ->filter()
            ->flip();

        foreach ($this->definitions() as $definition) {
            $systemKey = (string) data_get($definition, 'variables._system_key');
            if ($existingKeys->has($systemKey)) {
                continue;
            }

            $this->templates->create(
                $definition,
                'email-template-catalog:'.$companyId.':'.$systemKey,
            );
        }
    }

    public function definitions(): array
    {
        return [
            $this->definition(
                'system-maintenance-notice',
                'System Maintenance & Downtime Notice',
                'Scheduled System Maintenance & Downtime Notice',
                <<<'TEXT'
Hi Team,

Please be advised that the Emerald Rozalia system will undergo scheduled maintenance on [Date] from [Start Time] to [End Time].

During this window, the system will be temporarily unavailable. This update includes performance improvements and essential security patches to ensure a better experience for everyone.

Please ensure all your active tasks are saved before the maintenance window begins. If you have any urgent system-related concerns, please reach out directly.

Thank you for your cooperation.

Best regards,

[Your Name]
Super Admin / System Administrator
Emerald Rozalia Limited
TEXT,
                'System & Security',
                ['super-admin'],
                'none',
            ),
            $this->definition(
                'marketing-campaign-launch',
                'Marketing Campaign Launch & Assets',
                '🚀 Launching Our New Campaign: [Campaign Name]',
                <<<'TEXT'
Hi Team & Partners,

I am thrilled to announce the launch of our upcoming marketing campaign, "[Campaign Name]," officially rolling out on [Launch Date]!

This campaign focuses on [Brief Goal, e.g., boosting holiday sales / launching the new summer collection]. To ensure consistent branding across all channels, I have attached the official promotional materials and brand assets.

You can also access the full media kit here: [Insert Link to Assets]

Please review the guidelines before posting. If you need any custom graphics or have questions about the campaign strategy, feel free to reach out.

Best,

[Your Name]
Marketing Manager
Emerald Rozalia Limited
TEXT,
                'Marketing',
                ['marketing-manager'],
                'file_and_link',
            ),
            $this->definition(
                'store-inventory-stock-request',
                'Store Inventory Alert & Stock Request',
                'Inventory Alert & Stock Request for [Store Name / Location]',
                <<<'TEXT'
Hi Operations Team,

I am writing to request a stock replenishment for our [Store Name] branch. We are currently running low on the following high-demand items:

1. [Product Name/SKU] - Quantity Needed: [Number]
2. [Product Name/SKU] - Quantity Needed: [Number]

As we are expecting high foot traffic this coming weekend, having these items restocked by [Date] would be highly appreciated to avoid any loss in sales.

Please let me know once the delivery is scheduled.

Thank you,

[Your Name]
Store Manager - [Store Location]
Emerald Rozalia Limited
TEXT,
                'Store & Inventory',
                ['store-manager'],
                'none',
            ),
            $this->definition(
                'api-endpoint-key-rotation',
                'API Endpoint Updates & Key Rotation',
                'Important: API Endpoint Updates & Key Rotation',
                <<<'TEXT'
Hi Developer / Integration Partner,

This is an automated notification regarding your API usage with the Emerald Rozalia system.

We are updating our API infrastructure to improve performance and security. Effective [Date], the legacy endpoints (v1) will be deprecated. Please ensure your systems are migrated to the new v2 endpoints before this date.

Additionally, for security compliance, please rotate your API keys from your developer dashboard: [Insert Dashboard Link]

You can find the updated API documentation here: [Insert Docs Link]. Please reply to this email if you need technical assistance with the migration.

Regards,

API Support Team
Emerald Rozalia Limited
TEXT,
                'API & Integrations',
                ['api-user'],
                'link',
            ),
            $this->definition(
                'operations-weekly-summary',
                'Weekly Operations Summary',
                'Weekly Operations Summary: [Week Date Range]',
                <<<'TEXT'
Hi Management Team,

Please find the weekly operations summary for [Week Date Range] below:

* **Key Achievements:** [Briefly mention 1-2 completed tasks, e.g., successfully onboarded 2 new vendors].
* **Current Bottlenecks:** [Mention any delays or issues, e.g., logistics delay in the northern region].
* **Next Week's Focus:** [Briefly mention priorities for the upcoming week].

I have attached the detailed operational metrics report for your review. Please let me know if you would like to schedule a quick sync to discuss this further.

Best regards,

[Your Name]
Operations Manager
Emerald Rozalia Limited
TEXT,
                'Operations',
                ['operations-manager'],
                'file',
            ),
            $this->definition(
                'finance-invoice-payment',
                'Invoice & Payment Reminder',
                'Invoice #[Invoice_Number] from Emerald Rozalia',
                <<<'TEXT'
Hi [Client/Franchisee Name],

I hope this email finds you well.

Please find attached the invoice #[Invoice_Number] for [Service/Product/Franchise Fee]. The total amount due is [Amount], and the payment deadline is [Due_Date].

You can securely view and process your payment via the link below:
[Insert Payment Link]

If you have already made the payment, please disregard this email. For any billing-related questions or reconciliations, feel free to reply directly to this email.

Best regards,

[Your Name]
Finance Manager / Accountant
Emerald Rozalia Limited
TEXT,
                'Finance & Billing',
                ['finance-manager'],
                'file_and_link',
            ),
            $this->definition(
                'franchise-application-update',
                'Franchise Application Update',
                'Update on your Franchise Application with Emerald Rozalia',
                <<<'TEXT'
Hi [Applicant Name],

Thank you for your interest in partnering with Emerald Rozalia.

I am writing to provide an update on your recent franchise application. Our team has reviewed your initial submission, and we are excited to move forward to the next step.

Could you please provide the following additional documents for verification?
1. [Document 1]
2. [Document 2]

You can upload these documents directly to your application portal here: [Insert Portal Link].

If you have any questions about the franchise agreement or store requirements, I would be happy to schedule a brief call with you.

Warm regards,

[Your Name]
Franchise Manager
Emerald Rozalia Limited
TEXT,
                'Franchise',
                ['franchise-manager'],
                'link',
            ),
            $this->definition(
                'sales-lead-follow-up',
                'Sales Lead Follow-up',
                'Following up on your inquiry with Emerald Rozalia',
                <<<'TEXT'
Hi [Customer Name],

I hope you’re having a great day!

I am following up on your recent inquiry regarding [Product/Service Name]. I would love to understand your specific needs better and explore how our solutions can be the perfect fit for you.

Do you have 10 minutes this week for a quick chat? Please let me know what day and time work best for you, or you can book a slot directly on my calendar here: [Insert Calendar Link].

Looking forward to connecting with you.

Best regards,

[Your Name]
Sales Representative
Emerald Rozalia Limited
TEXT,
                'Sales',
                ['sales-representative'],
                'link',
            ),
            $this->definition(
                'support-ticket-resolution',
                'Support Ticket Resolution',
                'Resolution for Support Ticket #[Ticket_Number]',
                <<<'TEXT'
Hi [Customer Name],

Thank you for reaching out to Emerald Rozalia Support.

I am writing to inform you that our team has investigated the issue you reported regarding [Briefly mention the issue]. We have successfully resolved it from our end.

Could you please log in to your account and verify if everything is working smoothly now?

If you still need assistance or face any other issues, please reply directly to this email, and our communication support team will assist you immediately.

Thank you for your patience!

Best regards,

[Your Name]
Support Manager
Emerald Rozalia Limited
TEXT,
                'Customer Support',
                ['support-manager'],
                'none',
            ),
            $this->definition(
                'operations-urgent-order-delivery',
                'Urgent Operations / Vendor Delivery Update',
                'Urgent Update Required: Order / Delivery #[Order_Number]',
                <<<'TEXT'
Hi [Vendor/Partner Name],

I hope you are doing well.

I am reaching out to request an immediate status update on the daily operations regarding [Specific Task / Delivery / Inventory issue].

Please review the attached operational report and confirm if the timeline is on track. If there are any bottlenecks or delays expected, kindly notify me as soon as possible so we can adjust our schedules accordingly.

Your prompt response is highly appreciated.

Best,

[Your Name]
Operations Manager
Emerald Rozalia Limited
TEXT,
                'Operations',
                ['operations-manager'],
                'file',
            ),
        ];
    }

    private function definition(
        string $systemKey,
        string $name,
        string $subject,
        string $body,
        string $category,
        array $roles,
        string $attachmentPreference,
    ): array {
        return [
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'channel' => 'email',
            'status' => 'active',
            'variables' => [
                'category' => $category,
                'language' => 'en',
                '_system_key' => $systemKey,
                '_roles' => $roles,
                '_attachment_preference' => $attachmentPreference,
            ],
        ];
    }
}
