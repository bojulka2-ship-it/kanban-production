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
        Schema::create('stage_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained();
            // pending / in_progress / done
            $table->string('status', 20)->default('pending');
            // Дата входа в этап (заполняется для in_progress)
            $table->dateTime('entered_at')->nullable();
            $table->timestamps();

            // Одна карточка задачи на этапе
            $table->unique(['project_task_id', 'stage_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_statuses');
    }
};
