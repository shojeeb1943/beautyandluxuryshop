<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('order_status');
            $table->index('created_at');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->index('added_by');
            $table->index('code');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_status']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['added_by']);
            $table->dropIndex(['code']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
        });
    }
};
