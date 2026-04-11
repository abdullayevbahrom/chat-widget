<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Tenant\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Tenant\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Tenant\Resources\ProjectResource\RelationManagers\DomainsRelationManager;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Projects';

    protected static ?string $modelLabel = 'Project';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                \Filament\Forms\Components\Section::make('Project Details')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                                $set('slug', \Illuminate\Support\Str::slug($state));
                            }),
                        \Filament\Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(
                                table: Project::class,
                                column: 'slug',
                                ignoreRecord: true,
                            )
                            ->helperText('Used to identify this project in URLs.'),
                        \Filament\Forms\Components\Textarea::make('description')
                            ->nullable()
                            ->rows(3)
                            ->maxLength(1000),
                        \Filament\Forms\Components\TextInput::make('primary_domain')
                            ->nullable()
                            ->maxLength(255)
                            ->regex('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/')
                            ->helperText('e.g., app.example.com'),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                \Filament\Forms\Components\Section::make('Widget Configuration')
                    ->description('Configure how the widget appears on your site')
                    ->schema([
                        \Filament\Forms\Components\Select::make('settings.widget.theme')
                            ->label('Theme')
                            ->options([
                                'light' => 'Light',
                                'dark' => 'Dark',
                            ])
                            ->default('light'),
                        \Filament\Forms\Components\Select::make('settings.widget.position')
                            ->label('Position')
                            ->options([
                                'bottom-right' => 'Bottom Right',
                                'bottom-left' => 'Bottom Left',
                                'top-right' => 'Top Right',
                                'top-left' => 'Top Left',
                            ])
                            ->default('bottom-right'),
                        \Filament\Forms\Components\TextInput::make('settings.widget.width')
                            ->label('Width (px)')
                            ->numeric()
                            ->default(350)
                            ->minValue(300)
                            ->maxValue(600),
                        \Filament\Forms\Components\TextInput::make('settings.widget.height')
                            ->label('Height (px)')
                            ->numeric()
                            ->default(500)
                            ->minValue(400)
                            ->maxValue(800),
                        \Filament\Forms\Components\ColorPicker::make('settings.widget.primary_color')
                            ->label('Primary Color')
                            ->default('#3B82F6'),
                        \Filament\Forms\Components\Textarea::make('settings.widget.custom_css')
                            ->label('Custom CSS')
                            ->nullable()
                            ->rows(5)
                            ->helperText('Additional CSS to customize the widget appearance'),
                    ])
                    ->columns(2),

                \Filament\Forms\Components\Section::make('Widget Key')
                    ->description('Manage the widget key used to embed this widget on your site')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('widget_key_display')
                            ->label('Current Key')
                            ->content(function (?Project $record): string {
                                if ($record === null || $record->widget_key_prefix === null) {
                                    return 'No key generated. Generate one below.';
                                }

                                return $record->widget_key_prefix . '... (hidden for security)';
                            }),

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('generate_key')
                                ->label('Generate New Key')
                                ->icon(Heroicon::OutlinedKey)
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Generate new widget key?')
                                ->modalDescription('This will invalidate the old key. Your widget may stop working on sites using the old key until they update.')
                                ->modalSubmitActionLabel('Yes, generate new key')
                                ->action(function (Project $record) {
                                    $newKey = $record->generateWidgetKey();

                                    // Store the new key temporarily in session to show once
                                    session([
                                        'generated_widget_key_' . $record->id => $newKey,
                                    ]);

                                    return redirect()->back()->with([
                                        'alert' => "New widget key generated: {$newKey}",
                                    ]);
                                })
                                ->visible(fn (?Project $record): bool => $record !== null),

                            \Filament\Forms\Components\Actions\Action::make('revoke_key')
                                ->label('Revoke Key')
                                ->icon(Heroicon::OutlinedTrash)
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Revoke widget key?')
                                ->modalDescription('This will immediately disable the widget on all sites. You will need to generate a new key and update all embeds.')
                                ->action(function (Project $record) {
                                    $record->revokeWidgetKey();

                                    return redirect()->back();
                                })
                                ->visible(fn (?Project $record): bool => $record !== null && $record->widget_key_prefix !== null),
                        ]),
                    ]),

                \Filament\Forms\Components\Section::make('Embed Code')
                    ->description('Copy this code to your website to embed the widget')
                    ->schema([
                        \Filament\Forms\Components\ViewField::make('embed_code')
                            ->view('widget.embed-code')
                            ->viewData(function (?Project $record): array {
                                if ($record === null || $record->widget_key_prefix === null) {
                                    return ['project' => null, 'widgetKey' => null];
                                }

                                // Check if there's a newly generated key in session
                                $sessionKey = 'generated_widget_key_' . $record->id;
                                $widgetKey = session($sessionKey);

                                if ($widgetKey) {
                                    // Mark that we just generated a key so the view shows it once
                                    session(['just_generated_' . $record->id => true]);
                                    session()->forget($sessionKey);
                                }

                                return [
                                    'project' => $record,
                                    'widgetKey' => $widgetKey,
                                ];
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('widget_key_prefix')
                    ->label('Widget Key')
                    ->placeholder('No key')
                    ->formatStateUsing(fn (?string $state): string => $state ? $state.'...' : ''),
                \Filament\Tables\Columns\TextColumn::make('primary_domain')
                    ->label('Primary Domain')
                    ->placeholder('—'),
                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->modalDescription(function (?Project $record): ?string {
                        if ($record === null) {
                            return null;
                        }

                        $count = $record->activeConversationsCount();

                        if ($count > 0) {
                            return "⚠️ Ushbu loyihada {$count} ta faol suhbat mavjud. Loyihani o'chirish barcha suhbatlarni ham o'chiradi.";
                        }

                        return null;
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        if ($user === null || $user->tenant_id === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->where('tenant_id', $user->tenant_id);
    }
}
