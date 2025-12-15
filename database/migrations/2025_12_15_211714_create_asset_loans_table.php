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
        Schema::create('asset_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedBigInteger('resident_id');
            $table->integer('quantity');
            $table->date('loan_date');
            $table->dateTime('planned_return_date')->nullable();
            $table->dateTime('actual_return_date')->nullable();
            $table->enum('loan_status', [
                'requested',
                'borrowed',
                'returned',
                'rejected'
            ])->default('requested');
            $table->text('loan_reason')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_loans');
    }
};
