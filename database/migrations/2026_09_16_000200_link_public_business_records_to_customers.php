<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inquiries') && ! Schema::hasColumn('inquiries', 'customer_id')) {
            Schema::table('inquiries', function (Blueprint $table): void {
                $table->foreignId('customer_id')->nullable()->index()->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('franchise_applications') && ! Schema::hasColumn('franchise_applications', 'customer_id')) {
            Schema::table('franchise_applications', function (Blueprint $table): void {
                $table->foreignId('customer_id')->nullable()->index()->constrained('users')->nullOnDelete();
            });
        }

        $this->backfillVerifiedCustomerLinks();
    }

    private function backfillVerifiedCustomerLinks(): void
    {
        if (! Schema::hasTable('conversations')
            || ! Schema::hasColumn('conversations', 'customer_id')
            || ! Schema::hasColumn('conversations', 'inquiry_id')) {
            return;
        }

        DB::table('conversations')
            ->whereNotNull('customer_id')
            ->whereNotNull('inquiry_id')
            ->select(['id', 'customer_id', 'inquiry_id', 'contact'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $conversation) {
                    $user = DB::table('users')->where('id', $conversation->customer_id)->first(['id', 'email', 'email_verified_at']);
                    $inquiry = DB::table('inquiries')->where('id', $conversation->inquiry_id)->first(['id', 'email']);

                    $verifiedMatch = $user
                        && $inquiry
                        && $user->email_verified_at
                        && mb_strtolower(trim((string) $user->email)) === mb_strtolower(trim((string) $inquiry->email))
                        && mb_strtolower(trim((string) $user->email)) === mb_strtolower(trim((string) $conversation->contact));

                    if (! $verifiedMatch) {
                        DB::table('conversations')->where('id', $conversation->id)->update(['customer_id' => null]);
                        continue;
                    }

                    DB::table('inquiries')
                        ->where('id', $conversation->inquiry_id)
                        ->whereNull('customer_id')
                        ->update(['customer_id' => $user->id]);

                    if (Schema::hasColumn('franchise_applications', 'customer_id')) {
                        DB::table('franchise_applications')
                            ->where('inquiry_id', $conversation->inquiry_id)
                            ->whereNull('customer_id')
                            ->update(['customer_id' => $user->id]);
                    }

                    if (Schema::hasTable('sales_quotes')) {
                        DB::table('sales_quotes')
                            ->where('inquiry_id', $conversation->inquiry_id)
                            ->whereNull('customer_id')
                            ->update(['customer_id' => $user->id]);

                        $quoteIds = DB::table('sales_quotes')
                            ->where('inquiry_id', $conversation->inquiry_id)
                            ->where('customer_id', $user->id)
                            ->pluck('id');

                        if ($quoteIds->isNotEmpty() && Schema::hasTable('orders') && Schema::hasColumn('orders', 'quote_id')) {
                            DB::table('orders')
                                ->whereIn('quote_id', $quoteIds)
                                ->whereNull('user_id')
                                ->update(['user_id' => $user->id]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('franchise_applications') && Schema::hasColumn('franchise_applications', 'customer_id')) {
            Schema::table('franchise_applications', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_id');
            });
        }

        if (Schema::hasTable('inquiries') && Schema::hasColumn('inquiries', 'customer_id')) {
            Schema::table('inquiries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_id');
            });
        }
    }
};
