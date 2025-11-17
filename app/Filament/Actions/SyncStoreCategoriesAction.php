<?php
namespace App\Filament\Actions;

use Filament\Schemas\Schema;
use App\Filament\CustomImport;
use App\Models\Store;
use Konnco\FilamentImport\Actions\ImportAction;
use Konnco\FilamentImport\Actions\ImportField;

class SyncStoreCategoriesAction extends ImportAction
{
    public static function getDefaultName(): ?string
    {
        return 'sync-store-categories';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Sync Stores Categories (CSV)'))
            ->uniqueField('name')
            ->fields([
                ImportField::make('store_id')
                    ->label('Store ID')
                    ->required(),
                ImportField::make('store_name')
                    ->label('Store Name')
                    ->required(),
                ImportField::make('category_names')
                    ->label('Category Names')
                    ->required(),
                ImportField::make('status')
                    ->label('Status')
                    ->required(),
            ]);

        $this->action(function (Schema $schema): void {
            $data = $schema->getState();

            $selectedField = collect($data)
                ->except('fileRealPath', 'file', 'skipHeader');

            CustomImport::make(spreadsheetFilePath: $data['file'])
                ->fields($selectedField)
                ->formSchemas($this->fields)
                ->uniqueField($this->uniqueField)
                ->model(Store::class)
                ->disk('local')
                ->skipHeader((bool) $data['skipHeader'])
                ->execute();
        });

        // $this->action(function (ComponentContainer $form): void {
        //     $data = $form->getState();

        //     $selectedField = collect($data)
        //         ->except('fileRealPath', 'file', 'skipHeader');

        //     CustomImport::make(spreadsheetFilePath: $data['file'])
        //         ->fields($selectedField)
        //         ->formSchemas($this->fields)
        //         ->uniqueField($this->uniqueField)
        //         ->model(Store::class)
        //         ->disk('local')
        //         ->skipHeader((bool) $data['skipHeader'])
        //         ->execute();
        // });
    }
}
