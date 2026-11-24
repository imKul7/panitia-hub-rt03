<?php

namespace App\Http\Controllers;

use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        Access::ensure('view');

        $period = Workspace::period();
        $user = auth()->user();
        $memberships = $user->memberships()->with('division')
            ->where('event_period_id', $period->id)->where('is_active', true)->get();

        return view('panitia.profile', compact('period', 'user', 'memberships'));
    }

    public function update(Request $request)
    {
        Access::ensure('view');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        auth()->user()->update($data);
        Workspace::log('profile.updated', 'Profil pengguna diperbarui');

        return back()->with('success', 'Data profil berhasil diperbarui.');
    }

    public function changePassword(Request $request)
    {
        Access::ensure('view');

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        auth()->user()->update(['password' => $data['password']]);
        Workspace::log('password.changed', 'Pengguna mengganti kata sandi');
        $request->session()->regenerate();

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }

    /**
     * Foto akun disimpan pada disk privat. Endpoint ini hanya menampilkan
     * foto milik pengguna yang sedang login, bukan foto pengguna lain.
     */
    public function photo()
    {
        Access::ensure('view');

        $user = auth()->user();
        $path = $user->avatar_path;
        abort_unless($path && $this->isOwnedPhoto($path, $user->id), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($path, 'foto-profil-rt03', [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function storePhoto(Request $request)
    {
        Access::ensure('view');

        $request->validate([
            'photo' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:min_width=96,min_height=96,max_width=4096,max_height=4096',
            ],
        ], [
            'photo.required' => 'Pilih foto profil terlebih dahulu.',
            'photo.image' => 'Unggah gambar JPG, PNG atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'photo.dimensions' => 'Ukuran foto harus antara 96×96 dan 4096×4096 piksel.',
        ]);

        $user = auth()->user();
        $disk = Storage::disk('local');
        $path = $request->file('photo')->store('avatars/'.$user->id, 'local');

        abort_unless($path && $this->isOwnedPhoto($path, $user->id), 500, 'Foto tidak dapat disimpan.');

        $oldPath = $user->avatar_path;
        try {
            $user->update(['avatar_path' => $path]);
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }

        if ($oldPath && $oldPath !== $path && $this->isOwnedPhoto($oldPath, $user->id)) {
            try {
                $disk->delete($oldPath);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        Workspace::log('profile.photo.updated', 'Foto profil pengguna diperbarui', $user->id, 'user');

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function deletePhoto()
    {
        Access::ensure('view');

        $user = auth()->user();
        $oldPath = $user->avatar_path;

        if (!$oldPath) {
            return back()->with('success', 'Foto profil sudah kosong.');
        }

        $user->update(['avatar_path' => null]);
        if ($this->isOwnedPhoto($oldPath, $user->id)) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        Workspace::log('profile.photo.deleted', 'Foto profil pengguna dihapus', $user->id, 'user');

        return back()->with('success', 'Foto profil dihapus. Inisial akun tetap ditampilkan.');
    }

    private function isOwnedPhoto(string $path, int $userId): bool
    {
        $prefix = 'avatars/'.$userId.'/';
        $filename = substr($path, strlen($prefix));

        return str_starts_with($path, $prefix)
            && $filename !== ''
            && !str_contains($filename, '/')
            && !str_contains($filename, '\\')
            && !str_contains($filename, '..');
    }
}
