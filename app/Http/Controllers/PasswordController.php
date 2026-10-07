<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Страница смены собственного пароля.
     */
    public function edit(): View
    {
        return view('password.edit');
    }

    /**
     * Смена собственного пароля: проверка текущего через Hash::check.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Новый пароль должен быть не короче 8 символов',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Текущий пароль неверен'])->onlyInput();
        }

        // cast 'password' => 'hashed' сам превратит значение в bcrypt-хеш
        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Пароль обновлён');
    }
}
