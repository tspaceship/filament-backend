<?php

namespace TSpaceship\FilamentBackend\Commands;

use Doctrine\DBAL\Types;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Str;
use TSpaceship\FilamentBackend\Forms\Components\TranslatableField;

class MakeResourceCommand extends \Filament\Commands\MakeResourceCommand
{
    protected $signature = 'make:backend-resource {name?} {--soft-deletes} {--view} {--G|generate=1} {--S|simple} {--F|force} {--media=*} {--file=*} {--image=*}';

    public function handle(): int
    {
        $path = config('filament.resources.path', app_path('Filament/Resources/'));
        $namespace = config('filament.resources.namespace', 'App\\Filament\\Resources');

        $model = (string) Str::of($this->argument('name') ?? $this->askRequired('Model (e.g. `BlogPost`)', 'name'))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\');

        if (blank($model)) {
            $model = 'Resource';
        }

        $modelClass = (string) Str::of($model)->afterLast('\\');
        $modelNamespace = Str::of($model)->contains('\\') ?
            (string) Str::of($model)->beforeLast('\\') :
            '';
        $pluralModelClass = (string) Str::of($modelClass)->pluralStudly();

        $resource = "{$model}Resource";
        $resourceClass = "{$modelClass}Resource";
        $resourceNamespace = $modelNamespace;
        $namespace .= $resourceNamespace !== '' ? "\\{$resourceNamespace}" : '';
        $listResourcePageClass = "List{$pluralModelClass}";
        $manageResourcePageClass = "Manage{$pluralModelClass}";
        $createResourcePageClass = "Create{$modelClass}";
        $editResourcePageClass = "Edit{$modelClass}";
        $viewResourcePageClass = "View{$modelClass}";

        $baseResourcePath =
            (string) Str::of($resource)
                ->prepend('/')
                ->prepend($path)
                ->replace('\\', '/')
                ->replace('//', '/');

        $resourcePath = "{$baseResourcePath}.php";
        $resourcePagesDirectory = "{$baseResourcePath}/Pages";
        $listResourcePagePath = "{$resourcePagesDirectory}/{$listResourcePageClass}.php";
        $manageResourcePagePath = "{$resourcePagesDirectory}/{$manageResourcePageClass}.php";
        $createResourcePagePath = "{$resourcePagesDirectory}/{$createResourcePageClass}.php";
        $editResourcePagePath = "{$resourcePagesDirectory}/{$editResourcePageClass}.php";
        $viewResourcePagePath = "{$resourcePagesDirectory}/{$viewResourcePageClass}.php";

        if (! $this->option('force') && $this->checkForCollision([
            $resourcePath,
            $listResourcePagePath,
            $manageResourcePagePath,
            $createResourcePagePath,
            $editResourcePagePath,
            $viewResourcePagePath,
        ])) {
            return static::INVALID;
        }

        //*******************************************
        //todo check if it has is_static
        $modelSchema = ($modelNamespace !== '' ? $modelNamespace : 'App\Models').'\\'.$modelClass;
        $hasStatic = $this->fieldExist($modelSchema, 'is_static');
        $recordTitle = 'title';
        if ($this->fieldExist($modelSchema, 'name')) {
            $recordTitle = 'name';
        }
        //*******************************************

        $pages = '';
        $pages .= '\'index\' => Pages\\'.($this->option('simple') ? $manageResourcePageClass : $listResourcePageClass).'::route(\'/\'),';

        if (! $this->option('simple')) {
            $pages .= PHP_EOL."'create' => Pages\\{$createResourcePageClass}::route('/create'),";

            if ($this->option('view')) {
                $pages .= PHP_EOL."'view' => Pages\\{$viewResourcePageClass}::route('/{record:id}'),";
            }

            $pages .= PHP_EOL."'edit' => Pages\\{$editResourcePageClass}::route('/{record:id}/edit'),";
        }

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        $relations = '';

        if ($this->option('simple')) {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        } else {
            $relations .= PHP_EOL.'public static function getRelations(): array';
            $relations .= PHP_EOL.'{';
            $relations .= PHP_EOL.'    return [';
            $relations .= PHP_EOL.'        //';
            $relations .= PHP_EOL.'    ];';
            $relations .= PHP_EOL.'}'.PHP_EOL;
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        //*******************************************
        if ($hasStatic) {
            $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        foreach ($records as $record) {
                            if (!$record->is_static) {
                                $record->delete();
                            }
                        }
                    }),';
        } else {
            $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';
        }
        //*******************************************

        $eloquentQuery = '';

        if ($this->option('soft-deletes')) {
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';

            $eloquentQuery .= PHP_EOL.PHP_EOL.'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL.'{';
            $eloquentQuery .= PHP_EOL.'    return parent::getEloquentQuery()';
            $eloquentQuery .= PHP_EOL.'        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL.'            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL.'        ]);';
            $eloquentQuery .= PHP_EOL.'}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);
        $tableFilters = $this->indentString(
            $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make(),' : '//',
            4,
        );
        if ($this->fieldExist($modelSchema, 'active')) {
            $tableFilters = $this->indentString(
                'Tables\Filters\TernaryFilter::make(\'Active\')
                ->placeholder(\'All\')
                ->trueLabel(\'Yes\')
                ->falseLabel(\'No\')',
                4,
            );
        }
        if ($this->fieldExist($modelSchema, 'published')) {
            $tableFilters = $this->indentString(
                'Tables\Filters\TernaryFilter::make(\'Published\')
                ->placeholder(\'All\')
                ->trueLabel(\'Yes\')
                ->falseLabel(\'No\')',
                4,
            );
        }
        $this->copyStubToApp('Resource', $resourcePath, [
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'formSchema' => $this->option('generate') ? $this->getResourceFormSchema(
                ($modelNamespace !== '' ? $modelNamespace : 'App\Models').'\\'.$modelClass,
            ) : $this->indentString('//', 4),
            'model' => $model === 'Resource' ? 'Resource as ResourceModel' : $model,
            'modelClass' => $model === 'Resource' ? 'ResourceModel' : $modelClass,
            'namespace' => $namespace,
            'pages' => $this->indentString($pages, 3),
            'relations' => $this->indentString($relations, 1),
            'resource' => "{$namespace}\\{$resourceClass}",
            'resourceClass' => $resourceClass,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 4),
            'tableColumns' => $this->option('generate') ? $this->getResourceTableColumns(
                ($modelNamespace !== '' ? $modelNamespace : 'App\Models').'\\'.$modelClass
            ) : $this->indentString('//', 4),
            'tableFilters' => $tableFilters,
            'recordTitle' => $recordTitle,
        ]);

        if ($this->option('simple')) {
            $this->copyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $manageResourcePageClass,
            ]);
        } else {
            $this->copyStubToApp('ResourceListPage', $listResourcePagePath, [
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $listResourcePageClass,
            ]);

            $this->copyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\CreateRecord',
                'baseResourcePageClass' => 'CreateRecord',
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view')) {
                $this->copyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                    'resource' => "{$namespace}\\{$resourceClass}",
                    'resourceClass' => $resourceClass,
                    'resourcePageClass' => $viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make()->icon(\'heroicon-o-trash\'),';

            if ($this->option('soft-deletes')) {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->copyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'actions' => $this->indentString($editPageActions, 3),
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $editResourcePageClass,
            ]);
        }

        $this->info("Successfully created {$resource}!");

        return static::SUCCESS;
    }

    private function fieldExist(string $model, string $columnName): bool
    {
        $table = $this->getModelTable($model);

        foreach ($table->getColumns() as $column) {
            if (! Str::of($columnName)->startsWith('_') && ! Str::of($columnName)->endsWith('_')) {
                if (Str::of($column->getName())->exactly($columnName)) {
                    return true;
                }
            } elseif (Str::of($columnName)->startsWith('_') && ! Str::of($columnName)->endsWith('_')) {
                if (Str::of($column->getName())->endsWith($columnName)) {
                    return true;
                }
            } elseif (! Str::of($columnName)->startsWith('_') && Str::of($columnName)->endsWith('_')) {
                if (Str::of($column->getName())->startsWith($columnName)) {
                    return true;
                }
            } elseif (Str::of($columnName)->startsWith('_') && Str::of($columnName)->endsWith('_')) {
                if (Str::of($column->getName())->contains($columnName)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getResourceFormSchema(string $model): string
    {
        $table = $this->getModelTable($model);

        if (! $table) {
            return $this->indentString('//', 4);
        }

        $components = [];

        foreach ($table->getColumns() as $column) {
            if ($column->getAutoincrement()) {
                continue;
            }

            if (Str::of($column->getName())->is([
                'created_at',
                'deleted_at',
                'updated_at',
                'slug',
                'is_static',
                'seo_*',
                '*_token',
                'active',
                'published',
                'published_at',
            ])) {
                continue;
            }

            $componentData = [];

            $componentData['type'] = $type = match ($column->getType()::class) {
                Types\BooleanType::class => Forms\Components\Toggle::class,
                Types\DateType::class => Forms\Components\DatePicker::class,
                Types\DateTimeType::class => Forms\Components\DateTimePicker::class,
                Types\TextType::class => Forms\Components\Textarea::class,
                Types\JsonType::class => TranslatableField::class,
                default => Forms\Components\TextInput::class,
            };

            if ($type === Forms\Components\TextInput::class) {
                if (Str::of($column->getName())->contains(['email'])) {
                    $componentData['email'] = [];
                }

                if (Str::of($column->getName())->contains(['password'])) {
                    $componentData['password'] = [];
                }

                if (Str::of($column->getName())->contains(['phone', 'tel'])) {
                    $componentData['tel'] = [];
                }
            }

            if (Str::of($column->getName())->contains(['content', 'description', 'body'])) {
                $componentData['html'] = [];
            }

            if ($column->getNotnull()) {
                $componentData['required'] = [];
            }

            if (in_array($type, [Forms\Components\TextInput::class, Forms\Components\Textarea::class]) && ($length = $column->getLength())) {
                $componentData['maxLength'] = [$length];
            }

            $components[$column->getName()] = $componentData;
        }
        //*******************************************
        $media = $this->option('media');
        $files = $this->option('file');
        $images = $this->option('image');
        foreach ($media as $mediaItem) {
            $components[$mediaItem] = ['type' => Forms\Components\SpatieMediaLibraryFileUpload::class, 'image' => [], 'collection' => ['\''.Str::plural($mediaItem).'\'']];
        }
        //*******************************************

        $output = count($components) ? '' : '//';
        //*******************************************
        $output .= ' Forms\Components\Group::make()
                    ->schema([
                    Forms\Components\Card::make()
                    ->schema([';
        //*******************************************
        foreach ($components as $componentName => $componentData) {
            //*******************************************
            $isTranslatable = false;
            if (in_array($componentData['type'], [TranslatableField::class])) {
                $isTranslatable = true;
                $output .= 'TranslatableField';
                $output .= '::make(\'';
                $output .= $componentName;
                $output .= '\')';
                unset($componentData['type']);

                // Configuration
                foreach ($componentData as $methodName => $parameters) {
                    $output .= PHP_EOL;
                    $output .= '    ->';
                    $output .= $methodName;
                    $output .= '(';
                    $output .= implode('\', \'', $parameters);
                    $output .= ')';
                    if ($methodName === 'html') {
                        $output .= PHP_EOL;
                        $output .= '->columns(1)';
                    }
                }
                $output .= PHP_EOL;
                $output .= '->get()';
            } else {
                // Constructor
                $output .= (string) Str::of($componentData['type'])->after('Filament\\');
                $output .= '::make(\'';
                $output .= $componentName;
                $output .= '\')';

                unset($componentData['type']);

                // Configuration
                foreach ($componentData as $methodName => $parameters) {
                    $output .= PHP_EOL;
                    $output .= '    ->';
                    $output .= $methodName;
                    $output .= '(';
                    $output .= implode('\', \'', $parameters);
                    $output .= ')';
                }
            }
            //*******************************************

            // Termination
            $output .= ',';

            if (! (array_key_last($components) === $componentName)) {
                $output .= PHP_EOL;
            }
        }
        $output .= ']),';
        //*******************************************
        if ($this->fieldExist($model, 'published_')) {
            $output .= PHP_EOL;
            $output .= 'PublishFields::make(),';
        }
        if ($this->fieldExist($model, 'active')) {
            $output .= PHP_EOL;
            $output .= 'PublishFields::active()';
            if ($this->fieldExist($model, 'is_static')) {
                $output .= PHP_EOL;
                $output .= '->hidden(function ($record) {
                                        return $record && $record->is_static;
                                    })
                                    ->dehydrated(function (Page|null $record) {
                                        return !($record && $record->is_static);
                                    })';
            }
            $output .= ',';
        }
        if ($this->fieldExist($model, 'seo_')) {
            $output .= PHP_EOL;
            $output .= 'Seo::make(),';
        }
        $output .= '])->columnSpan(2),';
        //*******************************************

        return $this->indentString($output, 4);
    }

    protected function getResourceTableColumns(string $model): string
    {
        $table = $this->getModelTable($model);

        if (! $table) {
            return $this->indentString('//', 4);
        }

        $columns = [];

        foreach ($table->getColumns() as $column) {
            if ($column->getAutoincrement()) {
                continue;
            }

            if (Str::of($column->getName())->endsWith([
                '_token',
            ])) {
                continue;
            }

            if (Str::of($column->getName())->startsWith([
                'seo_',
            ])) {
                continue;
            }

            if (Str::of($column->getName())->contains([
                'password',
                'is_static',
                'content',
                'description',
                'body',
                'slug',
                'updated_at',
                'created_at',
            ])) {
                continue;
            }

            $columnData = [];

            if ($column->getType() instanceof Types\BooleanType) {
                if ($this->fieldExist($model, 'is_static')) {
                    $columnData['type'] = Tables\Columns\ToggleColumn::class;
                    $columnData['disabled'] = ['fn($record) => $record->is_static'];
                } else {
                    $columnData['type'] = Tables\Columns\ToggleColumn::class;
                }
            } else {
                $columnData['type'] = Tables\Columns\TextColumn::class;

                if ($column->getType()::class === Types\DateType::class) {
                    $columnData['date'] = [];
                }

                if ($column->getType()::class === Types\DateTimeType::class) {
                    $columnData['dateTime'] = [];
                }
            }
            if (Str::of($column->getName())->contains([
                'title',
                'name',
            ])) {
                if ($column->getType()::class === Types\JsonType::class) {
                    $columnData['searchable'] = ['[\''.$column->getName().'->en\',\''.$column->getName().'->ar\']'];
                } else {
                    $columnData['searchable'] = [];
                }
            }

            $columns[$column->getName()] = $columnData;
        }

        $output = count($columns) ? '' : '//';

        foreach ($columns as $columnName => $columnData) {
            // Constructor
            $output .= (string) Str::of($columnData['type'])->after('Filament\\');
            $output .= '::make(\'';
            $output .= $columnName;
            $output .= '\')';

            unset($columnData['type']);

            // Configuration
            foreach ($columnData as $methodName => $parameters) {
                $output .= PHP_EOL;
                $output .= '    ->';
                $output .= $methodName;
                $output .= '(';
                $output .= implode('\', \'', $parameters);
                $output .= ')';
            }

            // Termination
            $output .= ',';

            if (! (array_key_last($columns) === $columnName)) {
                $output .= PHP_EOL;
            }
        }

        return $this->indentString($output, 4);
    }
}
