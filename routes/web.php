<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Гость может только войти
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Все остальные страницы — только для авторизованных
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Главная — канбан-доска (матрица проектов × этапов)
    Route::get('/', [ProjectController::class, 'index'])->name('home');

    // Проекты (создание/редактирование — только руководитель, проверка в контроллере)
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    // Перемещения по доске (права на чужие задачи — этап прав)
    Route::post('/project-tasks/{projectTask}/move', [ProjectTaskController::class, 'move'])->name('project-tasks.move');
    Route::post('/project-tasks/{projectTask}/complete', [ProjectTaskController::class, 'complete'])->name('project-tasks.complete');
    Route::get('/project-tasks/{projectTask}/detail', [ProjectTaskController::class, 'detail'])->name('project-tasks.detail');

    // Смена собственного пароля
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Раздел «Пользователи» (руководитель проверяется inline, этап прав заменит на Policy)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
});
