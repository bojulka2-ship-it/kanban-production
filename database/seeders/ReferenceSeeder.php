<?php

namespace Database\Seeders;

use App\Models\Stage;
use App\Models\TaskType;
use Illuminate\Database\Seeder;

class ReferenceSeeder extends Seeder
{
    /**
     * Фиксированные справочники: этапы производства и типы задач.
     */
    public function run(): void
    {
        $stages = [
            'Планирование',
            'Закупка',
            'Производство',
            'Контроль качества',
            'Отгрузка',
        ];

        $taskTypes = [
            'Конструкторская документация',
            'Материалы и комплектующие',
            'Изготовление',
            'Испытания и приёмка',
        ];

        foreach ($stages as $sortOrder => $name) {
            Stage::create(['name' => $name, 'sort_order' => $sortOrder + 1]);
        }

        foreach ($taskTypes as $sortOrder => $name) {
            TaskType::create(['name' => $name, 'sort_order' => $sortOrder + 1]);
        }
    }
}