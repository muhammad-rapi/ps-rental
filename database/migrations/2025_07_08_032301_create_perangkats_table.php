<?php

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
        Schema::create('perangkats', function (Blueprint $table) {
            $table->id();
            $table->string('nama_perangkat');
            $table->string('keterangan')->nullable();
            $table->boolean('is_active');
            $table->string('merk');
            $table->string('nomor');
            $table->string('alamat_ip');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perangkats');
    }
};
