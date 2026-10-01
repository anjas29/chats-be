<?php

namespace App\Filament\Resources\Conversations\Tables;

use App\Enums\ConversationType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('Direct chat'),
                TextColumn::make('creator.name')->label('Created by'),
                TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Participants'),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Messages'),
                TextColumn::make('updated_at')
                    ->label('Last activity')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('type')->options(ConversationType::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
