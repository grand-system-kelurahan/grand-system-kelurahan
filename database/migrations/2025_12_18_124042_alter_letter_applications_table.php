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
        Schema::table('letter_applications', function (Blueprint $table) {
            $table->dropColumn(['approved_by_employee_id', 'approved_by_employee_name']);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')
                ->references(columns: 'id')
                ->on('users')
                ->onDelete('restrict');
            $table->unsignedBigInteger('submitted_by');
            $table->foreign('submitted_by')
                ->references(columns: 'id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_applications', function (Blueprint $table) {
            //
        });
    }
};
