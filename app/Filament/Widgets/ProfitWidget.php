<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Profit', 'Rp 500.000'),
        ];
    }
}
