<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_quotes')) {
            Schema::create('sales_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->foreignId('inquiry_id')->nullable()->unique()->constrained('inquiries')->nullOnDelete();
                $table->foreignId('franchise_application_id')->nullable()->index()->constrained('franchise_applications')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->index()->constrained('conversations')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('order_type', 40)->index();
                $table->string('status', 40)->default('submitted')->index();
                $table->string('currency_code', 3)->default('EUR');
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('shipping', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->json('line_items')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('correlation_id')->nullable()->index();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->string('conversion_key', 100)->nullable()->unique();
                $table->timestampTz('submitted_at')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->timestampTz('rejected_at')->nullable();
                $table->timestampTz('cancelled_at')->nullable();
                $table->timestampTz('converted_at')->nullable();
                $table->unsignedInteger('version')->default(1)->index();
                $table->timestampsTz();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'quote_id')) {
                    $table->foreignId('quote_id')->nullable()->index()->constrained('sales_quotes')->nullOnDelete();
                }
                if (! Schema::hasColumn('orders', 'inquiry_id')) {
                    $table->foreignId('inquiry_id')->nullable()->index()->constrained('inquiries')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (Schema::hasColumn('orders', 'inquiry_id')) {
                    $table->dropConstrainedForeignId('inquiry_id');
                }
                if (Schema::hasColumn('orders', 'quote_id')) {
                    $table->dropConstrainedForeignId('quote_id');
                }
            });
        }

        Schema::dropIfExists('sales_quotes');
    }
};
