<?php

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('conversation.id')->label('Conversation'),
                TextEntry::make('sender.name'),
                TextEntry::make('type')->badge(),
                TextEntry::make('body')->columnSpanFull()->placeholder('No text'),
                TextEntry::make('attachments_count')->state(fn ($record) => $record->attachments()->count())->label('Attachments'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('edited_at')->dateTime()->placeholder('Never'),
                TextEntry::make('deleted_at')->dateTime()->placeholder('Not deleted'),
            ]);
    }
}
