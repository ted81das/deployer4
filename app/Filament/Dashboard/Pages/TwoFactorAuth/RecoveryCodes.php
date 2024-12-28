<?php

namespace App\Filament\Dashboard\Pages\TwoFactorAuth;

use Filament\Pages\Page;

class RecoveryCodes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.dashboard.pages.two-factor-auth.recovery-codes';

    protected static bool $shouldRegisterNavigation = false;

    protected function getViewData(): array
    {
        return [
            'recoveryCodes' => auth()->user()->getRecoveryCodes(),
        ];
    }

    public function storedRecoveryCodes()
    {
        $this->redirect(TwoFactorAuth::getUrl());
    }
}
