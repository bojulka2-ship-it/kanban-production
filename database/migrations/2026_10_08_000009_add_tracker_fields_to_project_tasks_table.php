<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Поля трекера для задачи (этап 11). Все nullable/с дефолтом,
     * чтобы существующие данные остались валидными.
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority')->default('normal');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['description', 'due_date', 'priority']);
        });
    }
};
