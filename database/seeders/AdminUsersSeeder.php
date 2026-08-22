<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUsersSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('lauda.admin.name', 'Administrador General'));
        $email = strtolower(trim((string) config('lauda.admin.email')));
        $password = (string) config('lauda.admin.password', '');

        if ($email === '') {
            throw new RuntimeException(
                'LAUDA_ADMIN_EMAIL no está configurado.'
            );
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | CREACIÓN INICIAL
        |--------------------------------------------------------------------------
        |
        | Si el administrador todavía no existe necesitamos obligatoriamente
        | una contraseña.
        |
        */
        if (!$user) {
            if ($password === '') {
                throw new RuntimeException(
                    'LAUDA_ADMIN_PASSWORD es obligatorio para crear el administrador por primera vez.'
                );
            }

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);

            $this->command?->info(
                "Administrador creado correctamente: {$email}"
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR EXISTENTE
        |--------------------------------------------------------------------------
        |
        | Mantiene el usuario existente y asegura rol + verificación.
        | IMPORTANTE:
        | si LAUDA_ADMIN_PASSWORD está vacío, NO modifica su contraseña.
        |
        */
        $updates = [
            'name' => $name,
            'role' => 'admin',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ];

        /*
        |--------------------------------------------------------------------------
        | ROTACIÓN EXPLÍCITA DE CONTRASEÑA
        |--------------------------------------------------------------------------
        |
        | Solo cambia la contraseña cuando LAUDA_ADMIN_PASSWORD tiene valor.
        |
        */
        if ($password !== '') {
            $updates['password'] = Hash::make($password);
            $updates['must_change_password'] = false;
            $updates['password_changed_at'] = now();
        }

        $user->forceFill($updates)->save();

        $this->command?->info(
            $password !== ''
                ? "Administrador actualizado y contraseña renovada: {$email}"
                : "Administrador actualizado sin modificar contraseña: {$email}"
        );
    }
}
