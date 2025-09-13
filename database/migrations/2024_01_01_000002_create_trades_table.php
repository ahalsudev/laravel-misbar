<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->nullable();
            $table->foreignId('asset_id')->constrained();
            $table->string('symbol', 10);
            $table->enum('side', ['buy', 'sell']);
            $table->enum('type', ['market', 'limit', 'stop', 'stop_limit']);
            $table->enum('time_in_force', ['day', 'gtc', 'ioc', 'fok'])->default('day');
            $table->decimal('quantity', 12, 6);
            $table->decimal('price', 12, 6)->nullable();
            $table->decimal('stop_price', 12, 6)->nullable();
            $table->decimal('filled_quantity', 12, 6)->default(0);
            $table->decimal('filled_avg_price', 12, 6)->nullable();
            $table->enum('status', [
                'new', 'partially_filled', 'filled', 'done_for_day',
                'canceled', 'expired', 'replaced', 'pending_cancel',
                'pending_replace', 'accepted', 'pending_new', 'accepted_for_bidding',
                'stopped', 'rejected', 'suspended', 'calculated'
            ])->default('new');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['symbol', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};