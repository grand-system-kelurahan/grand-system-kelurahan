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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('national_number_id')->unique();
            $table->string('name');
            $table->string('gender');
            $table->string('place_of_birth');
            $table->date('date_of_birth');
            $table->string('religion');
            $table->string('rt');
            $table->string('rw');
            $table->string('education');
            $table->string('occupation');
            $table->string('marital_status');
            $table->string('citizenship');
            $table->string('blood_type');
            $table->string('disabilities');
            $table->string('father_name');
            $table->string('mother_name');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            $table->foreign('region_id')
                ->references('id')
                ->on('regions')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
