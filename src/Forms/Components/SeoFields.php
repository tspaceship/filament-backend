<?php

namespace TSpaceship\FilamentBackend\Forms\Components;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class SeoFields
{
    public static function make()
    {
        return Section::make(__('SEO'))
            ->schema([
                TranslatableField::make('seo_title')->label('Title')->get(),
                TranslatableField::make('seo_description')->label('Description')->get(),
                TranslatableField::make('seo_keywords')->label('Keywords')->get(),
                SpatieMediaLibraryFileUpload::make('seo_image')
                    ->image()
                    ->label('Image')
                    ->collection('seo_images'),
            ])
            ->collapsible()
            ->collapsed();
    }
}
