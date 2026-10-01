<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageType;
use App\Events\MessageDeleted;
use App\Models\Message;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('conversation.id')->label('Conversation'),
                TextColumn::make('sender.name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('body')
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')->options(MessageType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::removeAction(),
                self::restoreAction(),
            ]);
    }

    /**
     * Soft-delete a message and tell connected clients to drop it.
     */
    public static function removeAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Remove')
            ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false)
            ->after(fn (Message $record) => broadcast(new MessageDeleted($record)));
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->authorize(fn (): bool => auth()->user()?->isAdmin() ?? false);
    }
}
