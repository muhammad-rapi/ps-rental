<?php

namespace App\Filament\Widgets;

use App\Models\Paket;
use App\Models\TransaksiItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TopPackagesStats extends BaseWidget
{
    // protected static ?string $heading = 'Top 5 Paket Paling Populer';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Mendapatkan 5 paket teratas berdasarkan jumlah transaksi item
        $topPackages = TransaksiItem::query()
            ->whereHas('transaksi', function ($query) {
                $query->where('status', 'completed');
            })
            ->selectRaw('item_id, count(*) as total_sold')
            ->where('item_type', Paket::class)
            ->groupBy('item_id')
            ->orderByDesc('total_sold')
            ->limit(4)
            ->get();

        $stats = [];
        foreach ($topPackages as $item) {
            $paket = Paket::find($item->item_id);
            if ($paket) {
                $stats[] = Stat::make('Paket ' . $paket->nama, number_format($item->total_sold))
                    ->description('Total terjual');
            }
        }

        return $stats;
    }
}
