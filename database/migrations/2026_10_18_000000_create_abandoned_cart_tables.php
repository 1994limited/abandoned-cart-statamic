<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('abandoned-cart.database.connection');
    }

    public function up(): void
    {
        // Each Cargo cart seen since the addon was installed: who it's for,
        // what was in it when it last changed, and what's happened since.
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->string('cart_id')->unique();
            $table->string('site');
            $table->string('locale')->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('user_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('consent', 10)->nullable();
            $table->text('consent_text')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_field', 1)->nullable();
            $table->json('items')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('currency', 3)->nullable();
            $table->timestamp('cart_updated_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->string('skip_reason', 30)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('order_id')->nullable();
            $table->string('order_reference')->nullable();
            $table->bigInteger('order_total')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reminders_sent', 'cart_updated_at']);
            $table->index('email');
            $table->index('ordered_at');
        });

        // Each reminder emailed, its discount code, and whether it was used.
        Schema::create('abandoned_cart_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('abandoned_cart_id')->constrained('abandoned_carts')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('email')->nullable();
            // The address as the unsubscribed list keeps it, so the link
            // in the email works after the address itself is removed.
            $table->string('email_hash', 64)->nullable();
            $table->boolean('test')->default(false);
            $table->string('discount_handle')->nullable();
            $table->string('discount_code')->nullable();
            $table->boolean('discount_generated')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['test', 'sent_at']);
            $table->index(['abandoned_cart_id', 'step']);
        });

        // Addresses that unsubscribed, hashed: never emailed again.
        Schema::create('abandoned_cart_suppressions', function (Blueprint $table) {
            $table->string('email_hash', 64)->primary();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_suppressions');
        Schema::dropIfExists('abandoned_cart_reminders');
        Schema::dropIfExists('abandoned_carts');
    }
};
