<?php

namespace TSpaceship\FilamentBackend\Commands;

use Illuminate\Console\Command;

class FilamentBackendCommand extends Command
{
    public $signature = 'filament-backend';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
