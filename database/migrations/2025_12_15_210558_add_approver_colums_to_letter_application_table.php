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
            $table->string('approved_by_employee_id', 64)->nullable()->after('approval_date');
            $table->string('approved_by_employee_name', 100)->nullable()->after('approved_by_employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_applications', function (Blueprint $table) {
            $table->dropColumn(['approved_by_employee_id', 'approved_by_employee_name']);
        });
    }
};
