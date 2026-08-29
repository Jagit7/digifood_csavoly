<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'make:superadmin {email}';
    protected $description = 'Create a super administrator user (a jelszót biztonságosan, rejtett bevitellel kéri be)';

    public function handle()
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Érvénytelen e-mail cím: '.$email);

            return self::FAILURE;
        }

        // Korábban itt egy sima QueryException (unique constraint hiba)
        // dobódott volna egy csúnya, nyers PHP hibaüzenettel, ha már
        // létezett felhasználó ezzel az e-mail címmel - most inkább előre
        // ellenőrizzük, és egyértelmű, magyar nyelvű hibaüzenetet adunk.
        if (User::query()->where('email', $email)->exists()) {
            $this->error('Már létezik felhasználó ezzel az e-mail címmel: '.$email);

            return self::FAILURE;
        }

        // A jelszót SOSEM szabad parancssori argumentumként bekérni - egy
        // CLI argumentum a legtöbb rendszeren belekerül a shell
        // history-jába, és a parancs futása alatt más rendszerfelhasználók
        // számára is látható a folyamatlistában (pl. `ps aux`). Ehelyett
        // rejtett bevitellel (nem jelenik meg a képernyőn, és nem kerül
        // sehova naplózásra) kérjük be, duplán, hogy elkerüljük az
        // elgépelést.
        $password = (string) $this->secret('Super admin jelszava (nem fog megjelenni a képernyőn)');
        $passwordConfirmation = (string) $this->secret('Jelszó megerősítése');

        if ($password === '') {
            $this->error('A jelszó megadása kötelező.');

            return self::FAILURE;
        }

        if ($password !== $passwordConfirmation) {
            $this->error('A két jelszó nem egyezik.');

            return self::FAILURE;
        }

        if (strlen($password) < 6) {
            $this->error('A jelszónak legalább 6 karakter hosszúnak kell lennie.');

            return self::FAILURE;
        }

        // forceCreate(): a "role"/"is_active" mezők tudatosan nincsenek a
        // User modell fillable listájában (jogosultság-eszkalációs
        // védelem), ezért itt explicit kell megkerülni a tömeges
        // hozzárendelés védelmét.
        $user = User::forceCreate([
            'name'           => 'Super Admin',
            'email'          => $email,
            'password'       => Hash::make($password),
            'role'           => User::ROLE_SUPER_ADMIN,
            'is_active'      => true,
        ]);

        $this->info("Super admin created: {$user->email}");

        return self::SUCCESS;
    }
}