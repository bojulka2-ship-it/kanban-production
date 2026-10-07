<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\UserRole;

/**
 * Временная inline-проверка роли руководителя (этап прав заменит на Policy).
 */
trait AuthorizesManager
{
    protected function authorizeManager(): void
    {
        abort_unless(auth()->user()->role === UserRole::Manager, 403);
    }
}