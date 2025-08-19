<?php

use App\Models\Perangkat;
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
        Schema::create('pakets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Perangkat::class, 'perangkat_id')->constrained()->onDelete('cascade');
            $table->string('nama_paket');
            $table->string('keterangan')->nullable();
            $table->string('status');
            $table->integer('harga');
            $table->integer('durasi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pakets');
    }
};
