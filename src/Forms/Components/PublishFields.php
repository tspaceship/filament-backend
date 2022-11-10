<?php

namespace TSpaceship\FilamentBackend\Forms\Components;

use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;

class PublishFields
{
    public static function make()
    {
        return Card::make()
            ->schema([
                Toggle::make('published')
                    ->required()
                    ->reactive(),
                DatePicker::make('published_at')
                    ->required()
                    ->hidden(fn(\Closure $get) => !$get('published')),
            ]);
    }

    public static function active()
    {
        return Card::make()
            ->schema([
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
