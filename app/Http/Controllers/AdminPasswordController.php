<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminPasswordController extends Controller
{
    public function update(Request $request, User $user)
    {
        $admin = $request->user();
        abort_unless($admin && $admin->is_active && $admin->is_super_admin, 403,
            'Hanya Super Admin yang boleh mengatur ulang kata sandi akun.');

        abort_if($admin->is($user), 422,
            'Gunakan Profil Saya untuk mengganti kata sandi akun sendiri.');

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi baru harus berbeda dari kata sandi akun sebelumnya.',
            ]);
        }

        // Password is hashed by User::casts(). Rotating remember token revokes
        // remember-me cookies. Revoke database sessions when supported.
        DB::transaction(function () use ($user, $data) {
            $user->forceFill([
                'password' => $data['password'],
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->delete();
            }

            Workspace::log(
                'account.password.reset',
                'Super Admin melakukan reset kata sandi untuk akun '.$user->login_id,
                $user->id,
                'user'
            );
        });

        return back()->with('success',
            'Kata sandi akun '.$user->login_id.' berhasil direset. Berikan kata sandi baru secara pribadi.');
    }
}
