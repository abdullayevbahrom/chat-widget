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
                \Filament\Actions\DeleteAction::make(),
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
