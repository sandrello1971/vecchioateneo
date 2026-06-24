<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'atheneum:admin-create
        {--email= : Email dell\'amministratore}
        {--name= : Nome completo}
        {--password= : Password (se omessa viene generata)}';

    protected $description = 'Crea un nuovo amministratore della piattaforma. Funziona con zero admin esistenti (bootstrap istanza).';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $name  = trim((string) ($this->option('name') ?: $this->ask('Nome completo')));

        $passwordProvided = (string) $this->option('password');
        $generated = false;
        if ($passwordProvided === '') {
            $password = $this->generatePassword();
            $generated = true;
        } else {
            $password = $passwordProvided;
        }

        $validator = Validator::make(
            compact('email', 'name', 'password'),
            [
                'email'    => 'required|email',
                'name'     => 'required|string|max:255',
                'password' => 'required|string|min:12',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $err) {
                $this->error($err);
            }
            return self::INVALID;
        }

        if (Admin::where('email', $email)->withTrashed()->exists()) {
            $this->error("Esiste già un admin con email {$email}.");
            return self::FAILURE;
        }

        $admin = Admin::create([
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'is_active' => true,
        ]);

        Log::warning('[admin] account creato via CLI', [
            'admin_id' => $admin->id,
            'email'    => $admin->email,
        ]);

        $this->info("Admin creato: {$admin->email}");

        if ($generated) {
            $this->newLine();
            $this->warn('Password generata (mostrata una sola volta, copiala ora):');
            $this->line('  ' . $password);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function generatePassword(): string
    {
        // 16 caratteri leggibili, alta entropia.
        return Str::password(16, letters: true, numbers: true, symbols: true, spaces: false);
    }
}
