<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','email'], 'password'=>['required','string']]);
        if (!Auth::attempt([...$credentials, 'is_active'=>true])) {
            return back()->withErrors(['email'=>'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (!Access::can(Auth::user(), 'view')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email'=>'Akun belum mempunyai penugasan panitia aktif.']);
        }
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
