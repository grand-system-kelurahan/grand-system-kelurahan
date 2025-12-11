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
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_card_id');
            $table->unsignedBigInteger('resident_id');
            $table->string('relationship');
            $table->timestamps();

            $table->foreign('family_card_id')
                ->references('id')
                ->on('family_cards')
                ->onDelete('restrict');
            $table->foreign('resident_id')
                ->references('id')
                ->on('residents')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
