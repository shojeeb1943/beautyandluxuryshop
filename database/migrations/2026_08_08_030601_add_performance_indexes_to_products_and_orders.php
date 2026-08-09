<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('slug');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'order_status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'order_status']);
        });
    }
};
