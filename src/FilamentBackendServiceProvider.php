<?php

namespace TSpaceship\FilamentBackend;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TSpaceship\FilamentBackend\Commands\MakeResourceCommand;

class FilamentBackendServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('filament-backend')
            ->hasConfigFile()
            //->hasViews()
            //->hasMigration('create_filament-backend_table')
            ->hasCommand(MakeResourceCommand::class);
    }
}
