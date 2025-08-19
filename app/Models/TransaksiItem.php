<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransaksiItem extends Model
{
    protected $table = 'transaksi_items';

    protected $guarded = ['id'];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }
}
