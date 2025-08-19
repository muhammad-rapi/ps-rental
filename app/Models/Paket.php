<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paket extends Model
{
    protected $guarded = ['id'];

   

    public function transaksi()
    {
        return $this->morphMany(Transaksi::class, 'transaksi');
    }

    public function transaksiItems()
    {
        return $this->morphMany(TransaksiItem::class, 'item');
    }
}
