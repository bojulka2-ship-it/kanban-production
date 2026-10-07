<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Страница входа (только для гостей).
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Вход в систему.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Деактивированный аккаунт — отдельная ошибка под формой
        if ($user && ! $user->is_active) {
            return back()->withErrors(['status' => 'Аккаунт деактивирован'])->onlyInput('email');
        }

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['credentials' => 'Неверный email или пароль'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Выход из системы.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
