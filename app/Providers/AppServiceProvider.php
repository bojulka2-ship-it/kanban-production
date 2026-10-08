<?php

namespace App\Providers;

use App\Models\ProjectTask;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Счётчик просроченных задач текущего пользователя для пункта «Мои задачи» в шапке
        View::composer('layouts.app', function ($view) {
            $overdue = auth()->check()
                ? ProjectTask::query()->forResponsible(auth()->id())->dueOverdue()->count()
                : 0;

            $view->with('myTasksOverdue', $overdue);
        });
    }
}
