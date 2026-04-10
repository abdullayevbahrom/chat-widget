<?php

namespace App\Filament\Tenant\Resources\ProjectResource\Pages;

use App\Filament\Tenant\Resources\ProjectResource;
use App\Services\WidgetKeyService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        // Auto-generate widget key for new projects
        $service = app(WidgetKeyService::class);
        $key = $service->generateKey($this->getRecord());

        // Store in session to show once on the edit page
        session()->flash('project_widget_key', $key);

        Notification::make()
            ->title('Project Created')
            ->body('A widget key has been automatically generated. Copy it from the edit page — it will only be shown once.')
            ->success()
            ->send();
    }
}
