<?php

namespace App\Filament\Resources\Conversations\RelationManagers;

use App\Filament\Resources\Messages\Tables\MessagesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('sender.name'),
                TextColumn::make('type')->badge(),
                TextColumn::make('body')->limit(80)->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('deleted_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                MessagesTable::removeAction(),
                MessagesTable::restoreAction(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]));
    }
}
