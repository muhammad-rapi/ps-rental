<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $guarded = ['id'];

    
    protected static function booted()
    {
        static::creating(function ($transaksi) {
            $transaksi->user_id = auth()->id();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaksiItems()
    {
        return $this->hasMany(TransaksiItem::class);
    }

    public function transaksi()
    {
        return $this->morphTo();
    }

    public function perangkat()
    {
        return $this->belongsTo(Perangkat::class);
    }
}
