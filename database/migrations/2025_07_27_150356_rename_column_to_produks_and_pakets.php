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
        Schema::table('produks', function (Blueprint $table) {
            $table->renameColumn('nama_produk', 'nama');
        });

        Schema::table('pakets', function (Blueprint $table) {
            $table->renameColumn('nama_paket', 'nama');
        });

        Schema::table('perangkats', function (Blueprint $table) {
            $table->renameColumn('nama_perangkat', 'nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->renameColumn('nama', 'nama_produk');
        });

        Schema::table('pakets', function (Blueprint $table) {
            $table->renameColumn('nama', 'nama_paket');
        });

        Schema::table('perangkats', function (Blueprint $table) {
            $table->renameColumn('nama', 'nama_perangkat');
        });
    }
};
