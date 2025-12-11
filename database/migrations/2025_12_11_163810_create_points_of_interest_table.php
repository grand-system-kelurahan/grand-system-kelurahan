<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('type', 50)->nullable(); // kantor kelurahan, posyandu, sekolah, dll
            $table->text('description')->nullable();
            $table->text('encoded_geometry')->nullable(); // encoded polyline/point
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');
    }
};
