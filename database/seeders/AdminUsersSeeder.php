<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUsersSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'luisjadames@gmail.com'],
            [
                'name' => 'Administrador General',
                'password' => Hash::make('qwerty@luisjadames@gmail.com'),
                'role' => 'admin',
            ]
        );
        User::updateOrCreate(
            ['email' => 'laudaapi@gmail.com'],
            [
                'name' => 'Administrador General',
                'password' => Hash::make('qwerty@laudaapi@gmail.com'),
                'role' => 'admin',
            ]
        );
    }
}
