<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea un usuario del panel, o cambia la contraseña si el correo ya existe.
 *   php artisan vandu:usuario
 *   php artisan vandu:usuario ab@agenciavandu.com --nombre="Alvar Buenfil"
 */
class CrearUsuarioAdmin extends Command
{
    protected $signature = 'vandu:usuario {email? : Correo con el que vas a entrar} {--nombre= : Nombre a mostrar}';

    protected $description = 'Crea un usuario para el panel /admin o cambia su contraseña';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Correo');
        $existe = User::where('email', $email)->first();
        $nombre = $this->option('nombre') ?: ($existe?->name ?? $this->ask('Nombre', 'Alvar Buenfil'));

        $password = $this->secret($existe ? 'Nueva contraseña' : 'Contraseña');
        $confirmar = $this->secret('Repite la contraseña');

        $v = Validator::make(
            compact('email', 'nombre', 'password') + ['password_confirmation' => $confirmar],
            ['email' => 'required|email', 'nombre' => 'required|string|max:255', 'password' => 'required|min:8|confirmed'],
            ['password.min' => 'La contraseña debe tener al menos 8 caracteres.', 'password.confirmed' => 'Las contraseñas no coinciden.']
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        // Quien se crea desde la terminal es super admin
        User::updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => $password, 'activo' => true, 'rol_id' => \App\Models\Rol::where('todo', true)->value('id')]);

        $this->info($existe ? "Contraseña actualizada para {$email}." : "Usuario {$email} creado. Ya puedes entrar en /admin/login.");

        return self::SUCCESS;
    }
}
