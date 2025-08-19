<?php

namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaksi extends EditRecord
{
    protected static string $resource = TransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // protected function handleRecordUpdate(Model $record, array $data): Model
    // {
    //     // Get the old transaction items for comparison
    //     $oldItems = $record->transaksiItems->pluck('qty', 'item_id')->toArray();

    //     // Let Filament handle the default update logic first
    //     $record->update($data);

    //     // Get the new transaction items from the form data
    //     $newItems = collect($data['transaksiItems'])->pluck('qty', 'item_id')->toArray();

    //     // Calculate the difference and update stock
    //     foreach ($newItems as $itemId => $newQty) {
    //         $oldQty = $oldItems[$itemId] ?? 0;
    //         $diff = $newQty - $oldQty;

    //         if ($diff !== 0) {
    //             $produk = Produk::find($itemId);
    //             if ($produk) {
    //                 $produk->decrement('stok', $diff);
    //             }
    //         }
    //     }

    //     return $record;
    // }
}
