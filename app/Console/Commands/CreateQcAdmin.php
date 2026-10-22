<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateQcAdmin extends Command
{
    protected $signature = 'qc:create-admin {email?} {name?}';

    protected $description = 'Create an account that can access the QC Inspect application';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Nama pengguna');
        $email = $this->argument('email') ?: $this->ask('Email pengguna');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Kata sandi (minimal 12 karakter)');
        $confirmation = $this->secret('Ulangi kata sandi');

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Kata sandi harus minimal 12 karakter dan kedua input harus sama.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info('Akun QC Inspect berhasil dibuat.');

        return self::SUCCESS;
    }
}
