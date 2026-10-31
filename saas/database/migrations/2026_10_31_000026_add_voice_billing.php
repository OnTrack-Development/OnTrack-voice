<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->string('billing_interval', 16)->default('month');
        });
        Schema::create('voice_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('kashier_session_id', 120)->nullable();
            $table->string('kashier_transaction_id', 120)->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('EGP');
            $table->string('status', 24)->default('pending');
            $table->string('mode', 8);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('voice_payment_order_id')->nullable()->unique()
                ->constrained('voice_payment_orders')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voice_payment_order_id');
        });
        Schema::dropIfExists('voice_payment_orders');
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_minor','currency','billing_interval']);
        });
    }
};
