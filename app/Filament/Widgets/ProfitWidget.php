<?php

namespace App\Filament\Widgets;

use App\Models\Transaksi;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public $startDate;
    public $endDate;

    public function mount()
    {
        // Set nilai default filter
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->endOfMonth()->toDateString();
    }

    protected function getStats(): array
    {
        $query = Transaksi::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);

        // Hitung total total dengan `sum` dari relasi `items`
        $totalProfit = $query->sum('total');

        // Hitung total hari ini
        $totalHariIni = Transaksi::query()
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('total');

        // Hitung total minggu ini
        $totalMingguIni = Transaksi::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('total');

        // Hitung total bulan ini
        $totalBulanIni = Transaksi::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('total');

        return [
            Stat::make('Profit Hari Ini', 'Rp. ' . number_format($totalHariIni, 0, ',', '.'))
                ->description('Total total untuk hari ini')
                ->color('success'),
            Stat::make('Profit Minggu Ini', 'Rp. ' . number_format($totalMingguIni, 0, ',', '.'))
                ->description('Total total untuk minggu ini')
                ->color('info'),
            Stat::make('Profit Bulan Ini', 'Rp. ' . number_format($totalBulanIni, 0, ',', '.'))
                ->description('Total total untuk bulan ini')
                ->color('warning'),
            Stat::make('Total Profit', 'Rp. ' . number_format($totalProfit, 0, ',', '.'))
                ->description('Total total dari seluruh transaksi yang diselesaikan')
                ->color('primary'),
        ];
    }
}
