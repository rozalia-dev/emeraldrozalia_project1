<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ApplySeoRedirects;
use App\Http\Middleware\AttachRequestCorrelation;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminExportFormats;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureFranchisePermission;
use App\Http\Middleware\EnsureCommunicationPermission;
use App\Http\Middleware\InjectProductMoqField;
use App\Http\Middleware\InjectPublicChatWidget;
use App\Http\Middleware\InjectStorefrontLocalization;
use App\Http\Middleware\ResolveTenantContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web-entry.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/order-master.php');
            require base_path('routes/customers.php');
            require base_path('routes/user-system.php');
            require base_path('routes/chat-24-7.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', 'REMOTE_ADDR'));
        $middleware->validateCsrfTokens(except: [
            'api/v1/communication/webhooks/*',
            'api/v1/communication/email/inbound',
        ]);
        $middleware->appendToGroup('web', ResolveTenantContext::class);
        $middleware->appendToGroup('web', InjectStorefrontLocalization::class);
        $middleware->appendToGroup('web', AttachRequestCorrelation::class);
        $middleware->appendToGroup('web', EnsureAdminExportFormats::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'permission' => EnsurePermission::class,
            'franchise.permission' => EnsureFranchisePermission::class,
            'communication.permission' => EnsureCommunicationPermission::class,
        ]);
        $middleware->appendToGroup('web', ApplySeoRedirects::class);
        $middleware->appendToGroup('web', InjectProductMoqField::class);
        $middleware->appendToGroup('web', InjectPublicChatWidget::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
