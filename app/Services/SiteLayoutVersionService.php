<?php

namespace App\Services;

use App\Models\{Company, SiteLayoutVersion, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteLayoutVersionService
{
    public const ENVIRONMENTS = ['production', 'staging', 'development'];

    public const DEFAULT_REGIONS = [
        'header' => [
            'announcement' => [
                'eyebrow' => 'Proudly Manufacturing in Limerick, Ireland',
                'headline' => 'Irish Made. Limerick Born. Worn Everywhere.',
            ],
            'logo' => [
                'path' => '/assets/logo/logo_one_line.png',
                'alt' => 'Emerald Rozalia Limited',
            ],
            'primary_menu' => [
                ['label' => 'HOME', 'href' => '/'],
                ['label' => 'SHOP', 'href' => '/shop'],
                ['label' => 'COLLECTIONS', 'href' => '/collections'],
                ['label' => 'NEW ARRIVALS', 'href' => '/new-arrivals'],
                ['label' => 'CORPORATE ORDER', 'href' => '/corporate-orders'],
                ['label' => 'BULK ORDER', 'href' => '/bulk-orders'],
                ['label' => 'FRANCHISE APPLY', 'href' => '/franchise'],
                ['label' => 'HIRING APPLY', 'href' => '/careers'],
            ],
            'utility_menu' => [
                ['label' => 'Search', 'icon' => 'search', 'href' => '/shop'],
                ['label' => 'Account', 'guest_label' => 'Login', 'auth_label' => 'Account', 'icon' => 'user', 'href' => '/login', 'auth_href' => '/account'],
                ['label' => 'Cart', 'icon' => 'shopping-bag', 'href' => '/cart'],
            ],
        ],
        'footer' => [
            'brand_description' => 'Proudly manufacturing hats and caps in Limerick, Ireland.',
            'social_links' => [],
            'columns' => [
                [
                    'title' => 'SHOP',
                    'links' => [
                        ['label' => 'All Products', 'href' => '/shop'],
                        ['label' => 'Baseball Caps', 'href' => '/category/baseball-caps'],
                        ['label' => 'Bucket Hats', 'href' => '/category/bucket-hats'],
                        ['label' => 'Snapbacks', 'href' => '/category/snapbacks'],
                        ['label' => 'Flat Caps', 'href' => '/irish-traditional'],
                    ],
                ],
                [
                    'title' => 'COLLECTIONS',
                    'links' => [
                        ['label' => 'Irish Traditional', 'href' => '/irish-traditional'],
                        ['label' => 'Irish Heritage', 'href' => '/irish-heritage'],
                        ['label' => 'New Arrivals', 'href' => '/new-arrivals'],
                        ['label' => 'Premium Collection', 'href' => '/collections'],
                    ],
                ],
                [
                    'title' => 'CUSTOMER CARE',
                    'links' => [
                        ['label' => 'Size Guide', 'href' => '/factory'],
                        ['label' => 'Shipping & Delivery', 'href' => '/factory'],
                        ['label' => 'Returns & Refunds', 'href' => '/factory'],
                        ['label' => 'Contact Us', 'href' => '/contact'],
                    ],
                ],
                [
                    'title' => 'COMPANY',
                    'links' => [
                        ['label' => 'Our Story', 'href' => '/factory'],
                        ['label' => 'Manufacturing', 'href' => '/factory'],
                        ['label' => 'Sustainability', 'href' => '/global-network'],
                        ['label' => 'Careers', 'href' => '/careers'],
                    ],
                ],
            ],
            'newsletter' => [
                'enabled' => false,
                'title' => 'NEWSLETTER',
                'description' => 'Stay updated with new arrivals and offers.',
                'href' => '/contact',
                'cta_label' => 'Contact our team',
            ],
            'legal_links' => [
                ['label' => 'Privacy Policy', 'href' => '/factory'],
                ['label' => 'Terms & Conditions', 'href' => '/factory'],
            ],
        ],
        'policy' => [
            'contact_region' => 'footer',
            'approved_logo_assets_only' => true,
        ],
    ];

    public function defaults(): array
    {
        return self::DEFAULT_REGIONS;
    }

    /** @return Builder<SiteLayoutVersion> */
    public function query(?Company $company = null, string $environment = 'production', string $locale = 'en'): Builder
    {
        return SiteLayoutVersion::query()
            ->withoutGlobalScopes()
            ->where('scope', 'public')
            ->where('environment', $environment)
            ->where('locale', $locale)
            ->when(
                $company,
                fn (Builder $query): Builder => $query->where('company_id', $company->getKey()),
                fn (Builder $query): Builder => $query->whereNull('company_id'),
            );
    }

    public function currentCompany(): ?Company
    {
        return app(TenantContext::class)->company();
    }

    public function versions(?Company $company = null, string $environment = 'production', string $locale = 'en')
    {
        $company ??= $this->currentCompany();

        return $this->query($company, $environment, $locale)->latest('version')->get();
    }

    public function publicSnapshot(?Company $company = null, string $environment = 'production', ?string $locale = null): array
    {
        $company ??= $this->currentCompany();
        $locale ??= app()->getLocale() ?: 'en';
        $layout = $this->query($company, $environment, $locale)
            ->where('status', SiteLayoutVersion::STATUS_ACTIVE)
            ->latest('version')
            ->first();

        if (! $layout) {
            return [
                'meta' => [
                    'source' => 'default-layout-fallback',
                    'uuid' => null,
                    'version' => 0,
                    'status' => 'fallback',
                    'environment' => $environment,
                    'locale' => $locale,
                ],
                'regions' => self::DEFAULT_REGIONS,
            ];
        }

        return $this->snapshot($layout);
    }

    public function previewSnapshot(SiteLayoutVersion $layout): array
    {
        $this->assertCurrentContext($layout);

        return $this->snapshot($layout, 'draft-layout-preview');
    }

    public function urlFor(array $link): ?string
    {
        $href = trim((string) ($link['href'] ?? ''));
        if ($href === '' || str_starts_with($href, '#') || preg_match('/\A(?:javascript|data|vbscript):/i', $href)) {
            return null;
        }
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $href;
        }
        if (preg_match('/\Ahttps:\/\/[^\s]+\z/i', $href)) {
            return $href;
        }

        return null;
    }

    public function createDraft(array $attributes, ?User $user = null): SiteLayoutVersion
    {
        $company = $attributes['company'] ?? $this->currentCompany();
        $environment = (string) ($attributes['environment'] ?? 'production');
        $locale = (string) ($attributes['locale'] ?? app()->getLocale() ?: 'en');
        $regions = $this->validatedRegions($attributes['regions'] ?? self::DEFAULT_REGIONS);
        abort_unless(in_array($environment, self::ENVIRONMENTS, true), 422, 'Unsupported layout environment.');

        return DB::transaction(function () use ($attributes, $company, $environment, $locale, $regions, $user): SiteLayoutVersion {
            $version = ((int) $this->query($company, $environment, $locale)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->value('version')) + 1;
            $layout = SiteLayoutVersion::create([
                'company_id' => $company?->getKey(),
                'name' => trim((string) ($attributes['name'] ?? 'Emerald Rozalia Site Layout')),
                'scope' => 'public',
                'environment' => $environment,
                'locale' => $locale,
                'version' => $version,
                'status' => SiteLayoutVersion::STATUS_DRAFT,
                'regions' => $regions,
                'created_by' => $user?->getKey() ?? auth()->id(),
                'notes' => $attributes['notes'] ?? null,
            ]);

            AuditTrail::record('pages.layout.draft_created', $layout, null, $this->auditState($layout));

            return $layout;
        });
    }

    public function updateDraft(SiteLayoutVersion $layout, array $attributes, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless($layout->status === SiteLayoutVersion::STATUS_DRAFT, 409, 'Only draft layouts can be edited.');
        $regions = $this->validatedRegions($attributes['regions'] ?? $layout->regions ?? self::DEFAULT_REGIONS);
        $before = $this->auditState($layout);
        $layout->update([
            'name' => trim((string) ($attributes['name'] ?? $layout->name)),
            'regions' => $regions,
            'validation_errors' => null,
            'validated_at' => null,
            'validated_by' => null,
            'notes' => $attributes['notes'] ?? $layout->notes,
        ]);
        AuditTrail::record('pages.layout.draft_updated', $layout, $before, $this->auditState($layout->fresh()));

        return $layout->fresh();
    }

    public function validateLayout(SiteLayoutVersion $layout, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless($layout->status === SiteLayoutVersion::STATUS_DRAFT, 409, 'Only draft layouts can be validated.');
        $errors = $this->regionErrors($layout->regions ?? []);
        $before = $this->auditState($layout);
        if ($errors !== []) {
            $layout->update(['validation_errors' => $errors]);
            AuditTrail::record('pages.layout.validation_failed', $layout, $before, $this->auditState($layout->fresh()));
            throw ValidationException::withMessages($errors);
        }
        $layout->update([
            'validation_errors' => null,
            'validated_at' => now(),
            'validated_by' => $user?->getKey() ?? auth()->id(),
        ]);
        AuditTrail::record('pages.layout.validated', $layout, $before, $this->auditState($layout->fresh()));

        return $layout->fresh();
    }

    public function submitForApproval(SiteLayoutVersion $layout): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless($layout->status === SiteLayoutVersion::STATUS_DRAFT, 409, 'Only a draft layout can be submitted.');
        abort_unless($layout->validated_at, 409, 'Validate the layout before requesting approval.');

        return $this->transition($layout, SiteLayoutVersion::STATUS_PENDING_APPROVAL, 'pages.layout.submitted');
    }

    public function approve(SiteLayoutVersion $layout, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless($layout->status === SiteLayoutVersion::STATUS_PENDING_APPROVAL, 409, 'Only pending layouts can be approved.');
        $before = $this->auditState($layout);
        $layout->update([
            'status' => SiteLayoutVersion::STATUS_APPROVED,
            'approved_by' => $user?->getKey() ?? auth()->id(),
            'approved_at' => now(),
        ]);
        AuditTrail::record('pages.layout.approved', $layout, $before, $this->auditState($layout->fresh()));

        return $layout->fresh();
    }

    public function activate(SiteLayoutVersion $layout, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless($layout->status === SiteLayoutVersion::STATUS_APPROVED, 409, 'Approve the layout before activation.');

        return DB::transaction(function () use ($layout, $user): SiteLayoutVersion {
            $current = $this->query($layout->company, $layout->environment, $layout->locale)
                ->where('status', SiteLayoutVersion::STATUS_ACTIVE)
                ->whereKeyNot($layout->getKey())
                ->lockForUpdate()
                ->first();
            if ($current) {
                $before = $this->auditState($current);
                $current->update(['status' => SiteLayoutVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('pages.layout.superseded', $current, $before, $this->auditState($current->fresh()));
            }
            $before = $this->auditState($layout);
            $layout->update([
                'status' => SiteLayoutVersion::STATUS_ACTIVE,
                'activated_by' => $user?->getKey() ?? auth()->id(),
                'activated_at' => now(),
            ]);
            AuditTrail::record('pages.layout.activated', $layout, $before, $this->auditState($layout->fresh()));

            return $layout->fresh();
        });
    }

    public function disable(SiteLayoutVersion $layout, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($layout);
        abort_unless(in_array($layout->status, [SiteLayoutVersion::STATUS_ACTIVE, SiteLayoutVersion::STATUS_APPROVED], true), 409, 'This layout cannot be disabled from its current state.');
        $before = $this->auditState($layout);
        $layout->update([
            'status' => SiteLayoutVersion::STATUS_DISABLED,
            'disabled_by' => $user?->getKey() ?? auth()->id(),
            'disabled_at' => now(),
        ]);
        AuditTrail::record('pages.layout.disabled', $layout, $before, $this->auditState($layout->fresh()));

        return $layout->fresh();
    }

    public function rollback(SiteLayoutVersion $target, ?User $user = null): SiteLayoutVersion
    {
        $this->assertCurrentContext($target);
        abort_unless(! in_array($target->status, [SiteLayoutVersion::STATUS_DRAFT, SiteLayoutVersion::STATUS_DISABLED], true), 409, 'Only an approved, active or superseded layout can be rolled back.');

        return DB::transaction(function () use ($target, $user): SiteLayoutVersion {
            $current = $this->query($target->company, $target->environment, $target->locale)
                ->where('status', SiteLayoutVersion::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($current && $current->is($target)) {
                $target = $this->query($target->company, $target->environment, $target->locale)
                    ->where('version', '<', $current->version)
                    ->whereIn('status', [SiteLayoutVersion::STATUS_SUPERSEDED, SiteLayoutVersion::STATUS_APPROVED])
                    ->latest('version')->lockForUpdate()->first();
                abort_unless($target, 409, 'There is no prior layout version available for rollback.');
            }
            $before = $current ? $this->auditState($current) : null;
            if ($current) {
                $current->update(['status' => SiteLayoutVersion::STATUS_SUPERSEDED]);
                AuditTrail::record('pages.layout.superseded', $current, $before, $this->auditState($current->fresh()));
            }
            $version = ((int) $this->query($target->company, $target->environment, $target->locale)
                ->orderByDesc('version')->lockForUpdate()->value('version')) + 1;
            $userId = $user?->getKey() ?? auth()->id();
            $rollback = SiteLayoutVersion::create([
                'company_id' => $target->company_id,
                'name' => 'Rollback: '.$target->name,
                'scope' => $target->scope,
                'environment' => $target->environment,
                'locale' => $target->locale,
                'version' => $version,
                'status' => SiteLayoutVersion::STATUS_ACTIVE,
                'regions' => $this->validatedRegions($target->regions ?? self::DEFAULT_REGIONS),
                'source_version_uuid' => $target->uuid,
                'created_by' => $userId,
                'validated_by' => $userId,
                'validated_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'activated_by' => $userId,
                'activated_at' => now(),
                'rolled_back_by' => $userId,
                'rolled_back_at' => now(),
                'notes' => 'Created from an approved prior site layout version.',
            ]);
            AuditTrail::record('pages.layout.rolled_back', $rollback, $before, $this->auditState($rollback));

            return $rollback;
        });
    }

    public function auditState(SiteLayoutVersion $layout): array
    {
        return [
            'uuid' => $layout->uuid,
            'company_id' => $layout->company_id,
            'name' => $layout->name,
            'scope' => $layout->scope,
            'environment' => $layout->environment,
            'locale' => $layout->locale,
            'version' => (int) $layout->version,
            'status' => $layout->status,
            'regions' => $layout->regions,
            'source_version_uuid' => $layout->source_version_uuid,
            'validated_at' => $layout->validated_at?->toIso8601String(),
            'approved_at' => $layout->approved_at?->toIso8601String(),
            'activated_at' => $layout->activated_at?->toIso8601String(),
        ];
    }

    private function snapshot(SiteLayoutVersion $layout, string $source = 'active-layout-version'): array
    {
        return [
            'meta' => [
                'source' => $source,
                'uuid' => $layout->uuid,
                'version' => (int) $layout->version,
                'status' => $layout->status,
                'environment' => $layout->environment,
                'locale' => $layout->locale,
                'activated_at' => $layout->activated_at?->toIso8601String(),
            ],
            'regions' => $this->validatedRegions($layout->regions ?? self::DEFAULT_REGIONS),
        ];
    }

    private function transition(SiteLayoutVersion $layout, string $status, string $auditAction): SiteLayoutVersion
    {
        $before = $this->auditState($layout);
        $layout->update(['status' => $status]);
        AuditTrail::record($auditAction, $layout, $before, $this->auditState($layout->fresh()));

        return $layout->fresh();
    }

    private function assertCurrentContext(SiteLayoutVersion $layout): void
    {
        $companyId = $this->currentCompany()?->getKey();

        abort_unless((int) $layout->company_id === (int) $companyId, 404);
    }

    private function validatedRegions(array $regions): array
    {
        $regions = $this->mergeRegions(self::DEFAULT_REGIONS, $regions);
        $errors = $this->regionErrors($regions);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $regions;
    }

    /** @return array<string, string> */
    private function regionErrors(array $regions): array
    {
        $errors = [];
        foreach ([
            'header.announcement.eyebrow' => 180,
            'header.announcement.headline' => 180,
            'header.logo.alt' => 180,
            'footer.brand_description' => 500,
            'footer.newsletter.title' => 120,
            'footer.newsletter.description' => 500,
            'footer.newsletter.cta_label' => 120,
        ] as $path => $maxLength) {
            $value = data_get($regions, $path);
            if ($value !== null && (! is_string($value) || mb_strlen($value) > $maxLength)) {
                $errors[$path] = 'This public layout value is too long or has an invalid type.';
            }
        }
        $logoPath = data_get($regions, 'header.logo.path');
        if (! in_array($logoPath, ['/assets/logo/logo_one_line.png', '/assets/logo/logo_two_line.png'], true)) {
            $errors['header.logo.path'] = 'Use one of the approved Emerald Rozalia logo assets.';
        }
        foreach (['header.primary_menu', 'header.utility_menu', 'footer.columns'] as $path) {
            if (! is_array(data_get($regions, $path))) {
                $errors[$path] = 'This layout region must be an array of configured items.';
            }
        }
        foreach (['header.primary_menu', 'header.utility_menu'] as $path) {
            foreach ((array) data_get($regions, $path, []) as $index => $link) {
                if (! is_array($link) || trim((string) ($link['label'] ?? '')) === '') {
                    $errors[$path.'.'.$index.'.label'] = 'Every menu item needs a label.';
                }
                if (! is_array($link) || $this->urlFor($link) === null) {
                    $errors[$path.'.'.$index.'.href'] = 'Every menu item needs a safe local or HTTPS destination.';
                }
            }
        }
        foreach ((array) data_get($regions, 'footer.columns', []) as $columnIndex => $column) {
            if (! is_array($column) || trim((string) ($column['title'] ?? '')) === '') {
                $errors['footer.columns.'.$columnIndex.'.title'] = 'Every footer column needs a title.';
                continue;
            }
            foreach ((array) ($column['links'] ?? []) as $linkIndex => $link) {
                if (! is_array($link) || trim((string) ($link['label'] ?? '')) === '') {
                    $errors['footer.columns.'.$columnIndex.'.links.'.$linkIndex.'.label'] = 'Every footer link needs a label.';
                }
                if (! is_array($link) || $this->urlFor($link) === null) {
                    $errors['footer.columns.'.$columnIndex.'.links.'.$linkIndex.'.href'] = 'Every footer link needs a safe local or HTTPS destination.';
                }
            }
        }

        $newsletter = data_get($regions, 'footer.newsletter', []);
        if (is_array($newsletter) && ($newsletter['enabled'] ?? false) && blank($newsletter['href'] ?? null)) {
            $errors['footer.newsletter.href'] = 'An enabled newsletter must have a safe destination.';
        } elseif (is_array($newsletter) && filled($newsletter['href'] ?? null) && $this->urlFor($newsletter) === null) {
            $errors['footer.newsletter.href'] = 'The newsletter destination must be a safe local or HTTPS URL.';
        }
        foreach ((array) data_get($regions, 'footer.social_links', []) as $index => $social) {
            if (! is_array($social) || trim((string) ($social['label'] ?? '')) === '') {
                $errors['footer.social_links.'.$index.'.label'] = 'Every social profile needs a label.';
            }
            if (! is_array($social) || $this->urlFor($social) === null) {
                $errors['footer.social_links.'.$index.'.href'] = 'Every social profile needs a safe HTTPS destination.';
            }
        }
        foreach (['footer.legal_links'] as $path) {
            if (! is_array(data_get($regions, $path))) {
                $errors[$path] = 'This legal-link region must be an array of configured items.';
                continue;
            }
            foreach ((array) data_get($regions, $path, []) as $index => $link) {
                if (! is_array($link) || trim((string) ($link['label'] ?? '')) === '') {
                    $errors[$path.'.'.$index.'.label'] = 'Every legal link needs a label.';
                }
                if (! is_array($link) || $this->urlFor($link) === null) {
                    $errors[$path.'.'.$index.'.href'] = 'Every legal link needs a safe local or HTTPS destination.';
                }
            }
        }

        return $errors;
    }

    private function mergeRegions(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                is_array($value)
                && is_array($defaults[$key] ?? null)
                && ! array_is_list($value)
                && ! array_is_list($defaults[$key])
            ) {
                $defaults[$key] = $this->mergeRegions($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
