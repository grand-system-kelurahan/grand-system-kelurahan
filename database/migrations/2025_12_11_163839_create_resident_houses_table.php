<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_houses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('type', 50)->nullable(); // rumah warga / kantor / dsb (sesuai skema kamu)
            $table->text('description')->nullable();
            $table->text('encoded_geometry')->nullable(); // titik rumah
            $table->foreignId('resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_houses');
    }
};
