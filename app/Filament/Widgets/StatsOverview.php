<?php

namespace App\Filament\Widgets;

use App\Models\Message;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::count())
                ->description(User::where('is_banned', true)->count().' banned'),
            Stat::make('Active today', User::where('last_seen_at', '>=', now()->startOfDay())->count()),
            Stat::make('Messages today', Message::where('created_at', '>=', now()->startOfDay())->count())
                ->description(Message::count().' total'),
        ];
    }
}
