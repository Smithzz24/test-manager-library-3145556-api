<?php

namespace App\Infrastructure\Console;

use App\Application\Ports\Identifiers;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\Users;
use App\Domain\Operator;
use Illuminate\Console\Command;

final class ProvisionAdmin extends Command
{
    protected $signature = 'library:provision-admin';

    protected $description = 'Provision the initial administrator from environment credentials.';

    public function handle(Users $users, PasswordHasher $hashes, Identifiers $ids): int
    {
        $username = strtolower(trim((string) config('library.admin_username')));
        $password = (string) config('library.admin_password');
        if ($username === '' || $password === '' || mb_strlen($username) > 120) {
            $this->error('ADMIN_USERNAME y ADMIN_PASSWORD deben estar configurados correctamente.');

            return self::FAILURE;
        }
        $existing = $users->byUsername($username);
        if ($existing !== null) {
            if ($existing->role !== 'admin') {
                $this->error('El nombre configurado ya pertenece a un bibliotecario.');

                return self::FAILURE;
            }
            $this->info('El administrador ya existe; sus credenciales no se modificaron.');

            return self::SUCCESS;
        }
        $users->add(new Operator($ids->next(), $username, $hashes->hash($password), 'admin'));
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
