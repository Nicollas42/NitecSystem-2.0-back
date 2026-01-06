<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            $table->date('validade')->nullable()->after('quantidade');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            $table->dropColumn('validade');
        });
    }
};