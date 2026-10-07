<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Пользователи демо-данных: 1 руководитель и 5 сотрудников.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Иванов Иван', 'email' => 'manager@demo.ru', 'role' => UserRole::Manager],
            ['name' => 'Петров Пётр', 'email' => 'petrov@demo.ru', 'role' => UserRole::Employee],
            ['name' => 'Сидорова Мария', 'email' => 'sidorova@demo.ru', 'role' => UserRole::Employee],
            ['name' => 'Кузнецова Анна', 'email' => 'kuznetsova@demo.ru', 'role' => UserRole::Employee],
            ['name' => 'Смирнов Олег', 'email' => 'smirnov@demo.ru', 'role' => UserRole::Employee],
            ['name' => 'Волков Дмитрий', 'email' => 'volkov@demo.ru', 'role' => UserRole::Employee],
        ];

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('password'),
                'role' => $user['role'],
                'is_active' => true,
            ]);
        }
    }
}