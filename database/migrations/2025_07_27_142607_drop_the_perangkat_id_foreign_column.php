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
        Schema::table('pakets', function (Blueprint $table) {
            $table->dropForeign(['perangkat_id']);
            $table->dropColumn('perangkat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pakets', function (Blueprint $table) {
            $table->foreignIdFor(Perangkat::class, 'perangkat_id')->constrained()->onDelete('cascade');
        });
    }
};
