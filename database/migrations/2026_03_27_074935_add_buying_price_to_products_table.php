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
        Schema::table('products', function (Blueprint $table) {
            // Buying price for internal/admin use - nullable for backward compatibility with existing products
            $table->decimal('buying_price', 15, 4)->nullable()->after('unit_price')
                ->comment('Internal cost/buying price for profit calculations - admin only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('buying_price');
        });
    }
};
