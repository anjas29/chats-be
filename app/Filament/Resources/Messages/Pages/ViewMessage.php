<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use Filament\Resources\Pages\ViewRecord;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MessagesTable::removeAction(),
            MessagesTable::restoreAction(),
        ];
    }
}
