<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 30)->unique();
            $table->string('asset_name', 100);
            $table->text('description')->nullable();
            $table->enum('asset_type', ['item', 'room']);
            $table->integer('total_stock');
            $table->integer('available_stock');
            $table->string('location', 100)->nullable();
            $table->enum('asset_status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
