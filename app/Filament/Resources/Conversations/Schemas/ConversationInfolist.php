<?php

namespace App\Filament\Resources\Conversations\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')->badge(),
                TextEntry::make('name')->placeholder('Direct chat'),
                TextEntry::make('creator.name')->label('Created by'),
                TextEntry::make('created_at')->dateTime(),
                RepeatableEntry::make('participants')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('user.name')->label('User'),
                        TextEntry::make('role')->badge(),
                        TextEntry::make('joined_at')->dateTime(),
                        TextEntry::make('left_at')->dateTime()->placeholder('Active'),
                    ]),
            ]);
    }
}
