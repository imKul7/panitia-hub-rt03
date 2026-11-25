<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'panitia:admin';
    protected $description = 'Buat akun admin awal secara interaktif tanpa kata sandi default';

    public function handle(): int
    {
        $name=trim((string)$this->ask('Nama admin'));
        $email=trim((string)$this->ask('Email admin'));
        $password=(string)$this->secret('Password admin (minimal 8 karakter)');
        if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<8) {
            $this->error('Nama, email valid, dan password minimal 8 karakter wajib diisi.');
            return self::FAILURE;
        }
        if (User::where('email',$email)->exists()) {
            $this->error('Email sudah dipakai.');
            return self::FAILURE;
        }
        $admin = User::create(['name'=>$name,'email'=>$email,'password'=>$password,'is_active'=>true,'is_super_admin'=>true]);
        $this->info('Akun Super Admin dibuat. ID login: '.$admin->login_id.'. Jaga kredensialnya.');
        return self::SUCCESS;
    }
}
