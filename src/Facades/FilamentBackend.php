<?php

namespace TSpaceship\FilamentBackend\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TSpaceship\FilamentBackend\FilamentBackend
 */
class FilamentBackend extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \TSpaceship\FilamentBackend\FilamentBackend::class;
    }
}
