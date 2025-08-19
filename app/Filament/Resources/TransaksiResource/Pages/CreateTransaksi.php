<?php

namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use App\Models\Produk;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaksi extends CreateRecord
{
    protected static string $resource = TransaksiResource::class;

    protected function afterCreate(): void
    {
        // Ambil data dari form setelah transaksi dibuat
        $formData = $this->data;

        // Loop melalui item transaksi dan kurangi stok produk
        if (isset($formData['transaksiItems']) && is_array($formData['transaksiItems'])) {
            foreach ($formData['transaksiItems'] as $item) {
                // Cari produk dan kurangi stoknya
                $produk = Produk::find($item['item_id']);
                if ($produk) {
                    // Gunakan field 'jumlah' yang benar
                    $produk->decrement('stok', $item['jumlah']);
                }
            }
        }
    }
}
