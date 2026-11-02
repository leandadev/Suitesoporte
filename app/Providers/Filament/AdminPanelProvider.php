<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->brandName('LEANDATECH SOLUTION')
            ->brandLogo(fn () => view('filament.components.brand-logo'))
            ->brandLogoHeight('2.8rem')
            ->colors([
                'primary' => Color::hex('#2E62A6'),
                'info' => Color::hex('#049DD9'),
            ])
            ->renderHook(
                'panels::auth.login.form.before',
                fn () => view('filament.components.login-helper')
            )
            ->renderHook(
                'panels::head.end',
                fn () => view('filament.components.brand-styles')
            )
            ->renderHook(
                'panels::auth.login.form.after',
                fn () => new HtmlString('
                    <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(226, 232, 240, 0.8); font-size: 11px; color: #64748B; font-weight: 500; font-family: sans-serif;">
                        &copy; ' . date('Y') . ' <strong style="color: #2E62A6;">LEANDATECH SOLUTION</strong> · Todos los derechos reservados.
                    </div>
                ')
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}