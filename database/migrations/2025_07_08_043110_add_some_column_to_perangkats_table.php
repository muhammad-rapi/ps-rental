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
        Schema::table('perangkats', function (Blueprint $table) {
            $table->integer('adb_port')->default(5555)->after('alamat_ip');
            $table->boolean('auto_shutdown')->default(true)->after('adb_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('perangkats', function (Blueprint $table) {
            $table->dropColumn('adb_port');
            $table->dropColumn('auto_shutdown');
        });
    }
};
