<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->string('symbol', 10);
            $table->decimal('quantity', 12, 6);
            $table->enum('side', ['long', 'short']);
            $table->decimal('avg_entry_price', 12, 6);
            $table->decimal('market_value', 12, 2);
            $table->decimal('cost_basis', 12, 2);
            $table->decimal('unrealized_pl', 12, 2);
            $table->decimal('unrealized_plpc', 8, 4); // percent change
            $table->decimal('current_price', 12, 6);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
            
            $table->unique('symbol');
            $table->index(['symbol', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};