<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        if (! Schema::hasColumn('payment_transactions', 'company_id')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'company_id')) {
            DB::table('payment_transactions')
                ->whereNull('company_id')
                ->orderBy('id')
                ->chunkById(500, function ($payments): void {
                    foreach ($payments as $payment) {
                        $companyId = DB::table('orders')
                            ->where('id', $payment->order_id)
                            ->value('company_id');

                        if ($companyId !== null) {
                            DB::table('payment_transactions')
                                ->where('id', $payment->id)
                                ->whereNull('company_id')
                                ->update(['company_id' => $companyId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_transactions') || ! Schema::hasColumn('payment_transactions', 'company_id')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
