<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_banned')
                    ->label('Banned')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('last_seen_at')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                TernaryFilter::make('is_banned')->label('Banned'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::banAction(),
                self::unbanAction(),
            ]);
    }

    public static function banAction(): Action
    {
        return Action::make('ban')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The user will be signed out of every device and blocked from the API.')
            ->visible(fn (User $record): bool => ! $record->is_banned && $record->isNot(auth()->user()))
            ->action(fn (User $record) => $record->ban());
    }

    public static function unbanAction(): Action
    {
        return Action::make('unban')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->is_banned)
            ->action(fn (User $record) => $record->unban());
    }
}
