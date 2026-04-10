<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->required(fn ($component) => $component->getRecord() === null)
                    ->minLength(8)
                    ->same('passwordConfirmation')
                    ->maxLength(255),
                TextInput::make('passwordConfirmation')
                    ->password()
                    ->label('Password Confirmation')
                    ->dehydrated(false)
                    ->visible(fn ($component) => $component->getRecord() === null)
                    ->maxLength(255),
                Toggle::make('is_super_admin')
                    ->default(false)
                    ->disabled(fn ($component) => $component->getRecord()?->is_super_admin === false
                        || $component->getRecord()?->is(auth()->id()))
                    ->helperText(fn ($component) => $component->getRecord()?->is_super_admin === false
                        ? 'This cannot be enabled after creation.'
                        : ($component->getRecord()?->is(auth()->id())
                            ? 'You cannot remove your own super admin status.'
                            : null)),
            ]);
    }
}
