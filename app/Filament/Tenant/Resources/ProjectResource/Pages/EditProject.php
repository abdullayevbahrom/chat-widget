<?php

namespace App\Filament\Tenant\Resources\ProjectResource\Pages;

use App\Filament\Tenant\Resources\ProjectResource;
use App\Services\WidgetKeyService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public function form(Schema $schema): Schema
    {
        $record = $this->getRecord();

        return $schema
            ->columns(1)
            ->components([
                \Filament\Forms\Components\Section::make('Project Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, \Filament\Forms\Set $set) => $set('slug', \Illuminate\Support\Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(
                                table: \App\Models\Project::class,
                                column: 'slug',
                                ignoreRecord: true,
                            )
                            ->helperText('Used to identify this project in URLs.'),
                        Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->maxLength(1000),
                        TextInput::make('primary_domain')
                            ->nullable()
                            ->maxLength(255)
                            ->regex('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/')
                            ->helperText('e.g., app.example.com'),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                \Filament\Forms\Components\Section::make('Widget Key')
                    ->description('Manage your widget embed key. The key is shown only once when generated.')
                    ->schema([
                        \Filament\Forms\Components\ViewField::make('widget_key_display')
                            ->view('filament.forms.components.widget-key-display')
                            ->hidden(fn () => blank(session('project_widget_key')) && ! $record->hasWidgetKey()),
                    ])
                    ->footerActions([
                        Action::make('generateKey')
                            ->label('Generate Key')
                            ->icon(Heroicon::OutlinedKey)
                            ->visible(fn () => ! $record->hasWidgetKey())
                            ->requiresConfirmation()
                            ->modalDescription('A new widget key will be generated. You will see it only once.')
                            ->action(function () {
                                $service = app(WidgetKeyService::class);
                                $key = $service->generateKey($this->getRecord());
                                session()->flash('project_widget_key', $key);
                                $this->redirect(self::getUrl(['record' => $this->getRecord()->getKey()]));
                            }),
                        Action::make('regenerateKey')
                            ->label('Regenerate Key')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('warning')
                            ->visible(fn () => $record->hasWidgetKey())
                            ->requiresConfirmation()
                            ->modalDescription('Your current widget key will be revoked immediately. Any widgets using the old key will stop working.')
                            ->modalSubmitActionLabel('Regenerate')
                            ->action(function () {
                                $service = app(WidgetKeyService::class);
                                $key = $service->regenerateKey($this->getRecord());
                                session()->flash('project_widget_key', $key);
                                $this->redirect(self::getUrl(['record' => $this->getRecord()->getKey()]));
                            }),
                        Action::make('revokeKey')
                            ->label('Revoke Key')
                            ->icon(Heroicon::OutlinedTrash)
                            ->color('danger')
                            ->visible(fn () => $record->hasWidgetKey())
                            ->requiresConfirmation()
                            ->modalDescription('This will permanently revoke the current widget key. All embedded widgets will stop working.')
                            ->modalSubmitActionLabel('Revoke')
                            ->action(function () {
                                $service = app(WidgetKeyService::class);
                                $service->revokeKey($this->getRecord());
                                Notification::make()
                                    ->title('Widget Key Revoked')
                                    ->warning()
                                    ->send();
                                $this->redirect(self::getUrl(['record' => $this->getRecord()->getKey()]));
                            }),
                    ])
                    ->footerActionsAlignment(\Filament\Support\Enums\Alignment::End),

                \Filament\Forms\Components\Section::make('Widget Appearance')
                    ->schema([
                        Select::make('settings.widget.theme')
                            ->label('Theme')
                            ->options(['light' => 'Light', 'dark' => 'Dark'])
                            ->default('light')
                            ->nullable(),
                        Select::make('settings.widget.position')
                            ->label('Position')
                            ->options([
                                'bottom-right' => 'Bottom Right',
                                'bottom-left' => 'Bottom Left',
                                'top-right' => 'Top Right',
                                'top-left' => 'Top Left',
                            ])
                            ->default('bottom-right')
                            ->nullable(),
                        TextInput::make('settings.widget.width')
                            ->label('Width (px)')
                            ->numeric()
                            ->default(350),
                        TextInput::make('settings.widget.height')
                            ->label('Height (px)')
                            ->numeric()
                            ->default(500),
                        TextInput::make('settings.widget.primary_color')
                            ->label('Primary Color')
                            ->color()
                            ->default('#3B82F6')
                            ->nullable(),
                        Textarea::make('settings.widget.custom_css')
                            ->label('Custom CSS')
                            ->nullable()
                            ->rows(5)
                            ->extraAttributes(['class' => 'font-mono']),
                    ])
                    ->columns(2),
            ]);
    }

    public function mount(mixed $record): void
    {
        parent::mount($record);

        // Check if we have a newly generated key to show
        $newKey = session('project_widget_key');
        if ($newKey) {
            Notification::make()
                ->title('Widget Key Generated')
                ->body('Here is your widget key. Copy it now — it will not be shown again.')
                ->success()
                ->persistent()
                ->actions([
                    Action::make('copy')
                        ->label('Copy to Clipboard')
                        ->close(),
                ])
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}
