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
        Schema::create('letter_applications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('resident_id');
            $table->foreign('resident_id')->references('id')->on('residents')->onDelete('cascade');
            $table->unsignedBigInteger('letter_type_id');
            $table->foreign('letter_type_id')->references('id')->on('letter_types')->onDelete('cascade');

            $table->string('letter_number', 50)->nullable();
            $table->date('submission_date');
            $table->date('approval_date')->nullable();
            $table->string('status', 20)->default('new'); // new, approved, rejected, on progress
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_applications');
    }
};
