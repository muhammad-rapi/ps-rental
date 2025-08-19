<?php

use App\Models\Transaksi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('transaksi_items');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('transaksi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Transaksi::class, 'transaksi_id')->constrained()->onDelete('cascade');
            $table->integer('jumlah');
            $table->integer('harga');
            $table->integer('total');
            $table->timestamps();
        });
    }
};
