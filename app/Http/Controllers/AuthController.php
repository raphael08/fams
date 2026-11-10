<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string']);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $data['login'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            AuditService::log('LOGIN', null, null, 'User logged in');
            return redirect()->intended(route('dashboard'));
        }
        return back()->withInput($request->only('login'))->withErrors(['login' => 'Invalid credentials, or the account is disabled.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
