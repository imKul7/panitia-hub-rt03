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
        $credentials = $request->validate([
            'login_id' => ['required', 'string', 'max:24'],
            'password' => ['required', 'string'],
        ]);

        // Login ID maps directly to an existing users.id. Never derive the
        // password from the ID or store plaintext account passwords.
        $login = strtoupper(trim($credentials['login_id']));
        if (!preg_match('/^RT03-0*([1-9][0-9]{0,11})$/', $login, $match)
            || !Auth::attempt([
                'id' => (int) $match[1],
                'password' => $credentials['password'],
                'is_active' => true,
            ])) {
            return back()->withErrors(['login_id' => 'ID login atau kata sandi tidak sesuai.'])
                ->onlyInput('login_id');
        }

        $request->session()->regenerate();
        if (!Access::can(Auth::user(), 'view')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['login_id' => 'Akun belum mempunyai penugasan panitia aktif.'])
                ->onlyInput('login_id');
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
