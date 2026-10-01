<?php

namespace App\Filament\Widgets;

use App\Models\Message;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MessagesPerDayChart extends ChartWidget
{
    protected ?string $heading = 'Messages per day (last 14 days)';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $ago) => now()->subDays($ago)->startOfDay());

        $counts = Message::query()
            ->withTrashed()
            ->where('created_at', '>=', $days->first())
            ->get(['created_at'])
            ->countBy(fn (Message $message) => $message->created_at->toDateString());

        return [
            'datasets' => [
                [
                    'label' => 'Messages',
                    'data' => $days->map(fn (Carbon $day) => $counts->get($day->toDateString(), 0))->all(),
                ],
            ],
            'labels' => $days->map(fn (Carbon $day) => $day->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
