<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique();
            $table->string('name');
            $table->enum('asset_class', ['us_equity', 'crypto', 'forex', 'commodity']);
            $table->boolean('tradable')->default(true);
            $table->boolean('marginable')->default(false);
            $table->boolean('shortable')->default(false);
            $table->decimal('min_order_size', 12, 6)->nullable();
            $table->decimal('min_trade_increment', 12, 6)->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
            
            $table->index(['asset_class', 'tradable']);
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};