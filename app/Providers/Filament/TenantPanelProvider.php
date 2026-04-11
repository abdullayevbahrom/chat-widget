<?php

namespace App\Providers\Filament;

use App\Filament\Pages\TenantRegister;
use App\Http\Middleware\EnforceTenantContext;
use App\Http\Middleware\SetTenantContext;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('dashboard')
            ->brandName('ChatWidget')
            ->login()
            ->registration()
            ->passwordReset()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->font('Inter')
            ->viteTheme('resources/css/filament/tenant/theme.css')
            ->renderHook('panels::head.start', function () {
                return '
                    <style>
                        /* ===== AUTH LAYOUT ===== */
                        .fi-simple-layout {
                            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%) !important;
                            min-height: 100vh !important;
                            min-height: 100dvh !important;
                            display: flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                            padding: 2rem 1rem !important;
                        }

                        /* ===== AUTH CARD - Glass Morphism ===== */
                        .fi-simple-main {
                            background: rgba(255, 255, 255, 0.95) !important;
                            backdrop-filter: blur(12px) !important;
                            -webkit-backdrop-filter: blur(12px) !important;
                            border-radius: 20px !important;
                            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35) !important;
                            padding: 3rem 2.5rem !important;
                            width: 100% !important;
                            max-width: 440px !important;
                        }

                        /* ===== HEADER ===== */
                        .fi-simple-header {
                            text-align: center !important;
                            margin-bottom: 2rem !important;
                        }

                        /* ===== LOGO ===== */
                        .fi-logo {
                            display: flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                            gap: 0.75rem !important;
                            margin-bottom: 1rem !important;
                        }

                        .fi-logo::before {
                            content: " " !important;
                            display: block !important;
                            width: 40px !important;
                            height: 40px !important;
                            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
                            border-radius: 12px !important;
                            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
                        }

                        .fi-logo-text {
                            font-size: 1.25rem !important;
                            font-weight: 700 !important;
                            color: #1e1b4b !important;
                        }

                        .fi-simple-header-heading {
                            color: #1e1b4b !important;
                            font-weight: 800 !important;
                            font-size: 1.5rem !important;
                            margin-bottom: 0.25rem !important;
                            text-align: center !important;
                        }

                        /* ===== FORM GRID LAYOUT ===== */
                        .fi-grid-col {
                            display: flex !important;
                            flex-direction: column !important;
                            gap: 1.25rem !important;
                        }

                        /* ===== COMPONENTS SPACING ===== */
                        .fi-sc-component {
                            margin-bottom: 0 !important;
                            margin-top: 0 !important;
                        }

                        /* ===== INPUT FIELDS ===== */
                        .fi-input {
                            border-radius: 10px !important;
                            border: 2px solid #e2e8f0 !important;
                            background: #f8fafc !important;
                            padding: 0.75rem 1rem !important;
                            font-size: 0.95rem !important;
                            transition: all 0.2s ease !important;
                            width: 100% !important;
                            height: auto !important;
                        }

                        .fi-input:hover {
                            border-color: #cbd5e1 !important;
                        }

                        .fi-input:focus {
                            border-color: #6366f1 !important;
                            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12) !important;
                            background: white !important;
                            outline: none !important;
                        }

                        .fi-input::placeholder {
                            color: #94a3b8 !important;
                        }

                        /* Input wrapper */
                        .fi-input-wrp {
                            margin-bottom: 0 !important;
                        }

                        .fi-fo-text-input {
                            margin-top: 0.25rem !important;
                        }

                        /* ===== LABELS ===== */
                        .fi-fo-field-label {
                            color: #374151 !important;
                            font-weight: 600 !important;
                            font-size: 0.9rem !important;
                            margin-bottom: 0.5rem !important;
                        }

                        .fi-fo-field-label .fi-asterisk {
                            color: #ef4444 !important;
                            margin-left: 2px !important;
                        }

                        /* ===== FORGOT PASSWORD LINK ===== */
                        .fi-sc-text {
                            display: flex !important;
                            justify-content: flex-end !important;
                            margin-top: 0.25rem !important;
                            margin-bottom: 0.5rem !important;
                        }

                        .fi-sc-text .fi-link {
                            font-size: 0.85rem !important;
                        }

                        /* ===== REMEMBER ME CHECKBOX ===== */
                        .fi-fo-checkbox {
                            display: flex !important;
                            align-items: center !important;
                            gap: 0.5rem !important;
                        }

                        .fi-checkbox-input {
                            width: 16px !important;
                            height: 16px !important;
                            border-radius: 4px !important;
                            border: 2px solid #d1d5db !important;
                            cursor: pointer !important;
                            accent-color: #6366f1 !important;
                        }

                        .fi-checkbox-input:checked {
                            background-color: #6366f1 !important;
                            border-color: #6366f1 !important;
                        }

                        .fi-fo-field-label-ctn {
                            display: flex !important;
                            align-items: center !important;
                            gap: 0.5rem !important;
                        }

                        /* ===== BUTTONS ===== */
                        .fi-ac {
                            margin-top: 1.5rem !important;
                        }

                        .fi-btn {
                            border-radius: 12px !important;
                            font-weight: 600 !important;
                            transition: all 0.2s ease !important;
                            width: 100% !important;
                            padding: 0.875rem 1.5rem !important;
                            font-size: 1rem !important;
                        }

                        .fi-btn.fi-color-primary {
                            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
                            color: white !important;
                            border: none !important;
                            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35) !important;
                        }

                        .fi-btn.fi-color-primary:hover {
                            opacity: 0.95 !important;
                            transform: translateY(-2px) !important;
                            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.45) !important;
                        }

                        .fi-btn.fi-color-primary:active {
                            transform: translateY(0) !important;
                        }

                        .fi-btn.fi-color-primary .fi-btn-label {
                            color: white !important;
                        }

                        /* ===== LINKS ===== */
                        a.fi-link, .fi-link a {
                            color: #6366f1 !important;
                            font-weight: 500 !important;
                            transition: color 0.2s ease !important;
                        }

                        a.fi-link:hover, .fi-link a:hover {
                            color: #4338ca !important;
                            text-decoration: underline !important;
                        }

                        /* ===== RESPONSIVE ===== */
                        @media (max-width: 640px) {
                            .fi-simple-layout {
                                padding: 1rem !important;
                            }
                            .fi-simple-main {
                                padding: 2rem 1.5rem !important;
                                border-radius: 16px !important;
                            }
                            .fi-simple-heading {
                                font-size: 1.25rem !important;
                            }
                        }
                    </style>
                ';
            })
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->authGuard('tenant_user')
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
            ->authMiddleware([
                Authenticate::class,
                SetTenantContext::class,
                EnforceTenantContext::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->registration(TenantRegister::class);
    }
}
