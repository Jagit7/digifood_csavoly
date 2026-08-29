<?php

namespace App\Console\Commands;

use App\Support\CibSecretKey;
use Illuminate\Console\Command;
use RuntimeException;

class CibKeyfileToBase64Command extends Command
{
    protected $signature = 'cib:keyfile-to-base64 {path : A CIB .des kulcsfájl teljes elérési útja}';

    protected $description = 'Kiolvassa a CIB kulcsfájl utolsó 24 bájtját, és Base64 formában kiírja a terminálra.';

    public function handle(): int
    {
        try {
            $this->line(CibSecretKey::toBase64FromFile((string) $this->argument('path')));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
