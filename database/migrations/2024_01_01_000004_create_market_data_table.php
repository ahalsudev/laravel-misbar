<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_data', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10);
            $table->enum('data_type', ['quote', 'trade', 'bar', 'news']);
            $table->decimal('price', 12, 6)->nullable();
            $table->decimal('bid_price', 12, 6)->nullable();
            $table->decimal('ask_price', 12, 6)->nullable();
            $table->bigInteger('bid_size')->nullable();
            $table->bigInteger('ask_size')->nullable();
            $table->bigInteger('volume')->nullable();
            $table->decimal('high', 12, 6)->nullable();
            $table->decimal('low', 12, 6)->nullable();
            $table->decimal('open', 12, 6)->nullable();
            $table->decimal('close', 12, 6)->nullable();
            $table->decimal('vwap', 12, 6)->nullable();
            $table->timestamp('market_timestamp');
            $table->json('raw_data')->nullable();
            $table->timestamps();
            
            $table->index(['symbol', 'data_type', 'market_timestamp']);
            $table->index(['symbol', 'market_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_data');
    }
};