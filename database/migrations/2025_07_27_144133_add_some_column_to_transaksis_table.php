<?php

use App\Models\Perangkat;
use App\Models\User;
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
        Schema::table('transaksis', function (Blueprint $table) {
            $table->foreignIdFor(Perangkat::class, 'perangkat_id')
                ->nullable()
                ->constrained('perangkats')
                ->onDelete('set null');
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_jeda')->nullable();
            $table->timestamp('waktu_berakhir')->nullable();
            $table->integer('durasi_aktual_detik')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropForeign(['perangkat_id']);
            $table->dropColumn('perangkat_id');
            $table->dropColumn('waktu_mulai');
            $table->dropColumn('waktu_jeda');
            $table->dropColumn('waktu_berakhir');
            $table->dropColumn('durasi_aktual_detik');
        });
    }
};

