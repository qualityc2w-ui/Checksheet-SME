<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'  => 'Demo Admin',
                'email' => 'admin@example.test',
                'role'  => 'admin',
            ],
            [
                'name'  => 'Demo Inspector',
                'email' => 'inspector@example.test',
                'role'  => 'inspector',
            ],
            [
                'name'  => 'Demo Viewer',
                'email' => 'viewer@example.test',
                'role'  => 'viewer',
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name'     => $user['name'],
                    'password' => Hash::make('DemoPassword123!'),
                    'role'     => $user['role'],
                ]
            );
        }
    }
}
