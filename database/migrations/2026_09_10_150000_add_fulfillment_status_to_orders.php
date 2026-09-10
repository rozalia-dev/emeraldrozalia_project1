<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfillment_status', 40)->default('pending')->index();
        });

        DB::table('orders')->where('status', 'pending')->update(['fulfillment_status' => 'on_hold']);
        DB::table('orders')->where('status', 'approved')->update(['fulfillment_status' => 'ready_to_ship']);
        DB::table('orders')->where('status', 'processing')->update(['fulfillment_status' => 'picking']);
        DB::table('orders')->where('status', 'shipped')->update(['fulfillment_status' => 'shipped']);
        DB::table('orders')->where('status', 'completed')->update(['fulfillment_status' => 'delivered']);
        DB::table('orders')->whereIn('status', ['cancelled', 'refunded'])->update(['fulfillment_status' => 'closed']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('fulfillment_status');
        });
    }
};
