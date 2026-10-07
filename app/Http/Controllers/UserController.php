<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\AuthorizesManager;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesManager;
    /**
     * Список пользователей (только руководитель).
     */
    public function index(): View
    {
        $this->authorizeManager();

        return view('users.index', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    /**
     * Создание пользователя.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:manager,employee'],
        ], [
            'email.unique' => 'Пользователь с таким email уже существует',
            'password.min' => 'Пароль должен быть не короче 8 символов',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
            'is_active' => true,
        ]);

        return back()->with('success', 'Пользователь создан');
    }

    /**
     * Редактирование пользователя (имя, email, роль).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:manager,employee'],
        ], [
            'email.unique' => 'Пользователь с таким email уже существует',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::from($data['role']),
        ]);

        return back()->with('success', 'Данные обновлены');
    }

    /**
     * Сброс пароля пользователя (задаётся вручную, минимум 8 символов).
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManager();

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.min' => 'Пароль должен быть не короче 8 символов',
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Пароль пользователя сброшен');
    }

    /**
     * Деактивация / активация без удаления.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManager();

        // Нельзя лишить доступа самого себя
        if ($user->is($request->user())) {
            return back()->with('error', 'Нельзя деактивировать собственный аккаунт');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Пользователь активирован' : 'Пользователь деактивирован');
    }
}
