<?php

namespace TSpaceship\FilamentBackend\Forms\Components;

use Filament\Forms\Components\Group;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TranslatableField
{
    protected $field;
    protected $label;
    protected $type = 'TextInput';
    protected $requiredLanguages = [];
    protected $url = false;
    protected $dependant;
    protected $forceLtr;
    protected $columns = 2;
    protected $afterStateUpdated = [];
    protected $lazy = false;
    protected $isHtml = false;
    protected $helperText = '';
    protected $hiddenOn = '';
    protected $hidden = '';
    protected $languages;

    public function __construct($field)
    {
        $this->field = $field;
        $this->languages = config('filament-backend.languages');
    }

    public static function make(string $field): static
    {
        return app(static::class, ['field' => $field]);
    }

    public function required()
    {
        $this->requiredLanguages = collect($this->languages)->pluck('code')->toArray();
        return $this;
    }

    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    public function hiddenOn($value)
    {
        $this->hiddenOn = $value;
        return $this;
    }

    public function hidden($value)
    {
        $this->hidden = $value;
        return $this;
    }

    public function helperText($text)
    {
        $this->helperText = $text;
        return $this;
    }

    public function requiredOnly(array $languages)
    {
        $this->requiredLanguages = $languages;
        return $this;
    }

    public function url()
    {
        $this->url = true;
        return $this;
    }

    public function html()
    {
        $this->isHtml = true;
        return $this;
    }

    public function forceLtr()
    {
        $this->forceLtr = true;
        return $this;
    }

    public function dependant($value)
    {
        $this->dependant = $value;
        return $this;
    }

    public function label(string $label)
    {
        $this->label = $label;
        return $this;
    }

    public function columns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    private function setLabel($label)
    {
        $this->label = (string)Str::of($label)
            ->afterLast('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();
    }

    public function lazy(): static
    {
        $this->lazy = true;
        return $this;
    }

    public function get()
    {
        $schema = [];
        if ($this->isHtml) {
            $type = '\FilamentTiptapEditor\TiptapEditor';
        } else {
            $type = '\Filament\Forms\Components\\' . $this->type;
        }

        usort($this->languages, function ($a, $b) {
            return $a['code'] === app()->getLocale() ? -1 : 1;
        });

        foreach ($this->languages as $language) {
            if (!$this->label) {
                $this->setLabel($this->field);
            }
            $input = $type::make($this->field . '.' . $language['code'])
                ->label(self::transLabel($this->label, $language['label']))
                ->validationAttribute($this->label);

            if (in_array($language['code'], $this->requiredLanguages)) {
                $input->required();
            }


            if (isset($this->afterStateUpdated[$language['code']])) {
                $input->afterStateUpdated($this->afterStateUpdated[$language['code']]);
            }


            $input->extraAttributes(['dir' => $this->forceLtr ? 'ltr' : $language['dir']]);
            if ($this->isHtml) {
                $input->extraInputAttributes(['style' => 'min-height: 12rem;', 'dir' => $this->forceLtr ? 'ltr' : $language['dir']]);
            }

            if ($this->url) {
                $input->url();
            }
            if ($this->dependant) {
                $input->hidden(fn(\Closure $get) => !$get($this->dependant));
                $input->required(fn(\Closure $get) => $get($this->dependant));
            }
            if ($this->lazy) {
                $input->lazy();
            }
            if ($this->helperText) {
                $input->helperText($this->helperText);
            }
            if ($this->hiddenOn) {
                $input->hiddenOn($this->hiddenOn);
            }
            if ($this->hidden) {
                $input->hidden($this->hidden);
            }
            $schema[] = $input;
        }
        $group = Group::make()
            ->schema($schema)->hidden($this->hidden);
        return $group->columns($this->columns);
    }

    public static function transLabel(string $label, string $language): HtmlString
    {
        return new HtmlString(__($label) . ' <span class="text-xs text-gray-500">[' . __($language) . ']</span>');
    }

    public function afterStateUpdated($callbacks)
    {
        $this->afterStateUpdated = $callbacks;
        return $this;
    }
}
