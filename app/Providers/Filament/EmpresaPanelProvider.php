<?php

declare(strict_types=1);

namespace App\Providers\Filament;

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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class EmpresaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('empresa')
            ->path('gestion')
            ->login()
            ->profile(isSimple: false)
            ->authGuard('web')
            ->revealablePasswords(false)
            ->brandName('Facturación Electrónica')
            ->colors(['primary' => Color::Emerald])
            ->discoverResources(in: app_path('Filament/Empresa/Resources'), for: 'App\\Filament\\Empresa\\Resources')
            ->discoverPages(in: app_path('Filament/Empresa/Pages'), for: 'App\\Filament\\Empresa\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Empresa/Widgets'), for: 'App\\Filament\\Empresa\\Widgets')
            ->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
