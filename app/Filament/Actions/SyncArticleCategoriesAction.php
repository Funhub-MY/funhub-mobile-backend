<?php
namespace App\Filament\Actions;

use App\Filament\ArticleCustomImport;
use App\Filament\CustomImport;
use App\Models\Article;
use App\Models\Store;
use Filament\Forms\ComponentContainer;
use Konnco\FilamentImport\Actions\ImportAction;
use Konnco\FilamentImport\Actions\ImportField;

class SyncArticleCategoriesAction extends ImportAction
{
    public static function getDefaultName(): ?string
    {
        return 'sync-article-categories';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Sync Article Categories (CSV)'))
            ->uniqueField('title')
            ->fields([
                ImportField::make('article_id')
                    ->label('Article ID')
                    ->required(),
				ImportField::make('article_title')
					->label('Article Title')
					->required(),
                ImportField::make('category_names')
                    ->label('Category Names')
                    ->required(),
            ]);

        $this->action(function (ComponentContainer $form): void {
            $data = $form->getState();

            $selectedField = collect($data)
                ->except('fileRealPath', 'file', 'skipHeader');

            ArticleCustomImport::make(spreadsheetFilePath: $data['file'])
                ->fields($selectedField)
                ->formSchemas($this->fields)
                ->uniqueField($this->uniqueField)
                ->model(Article::class)
                ->disk('local')
                ->skipHeader((bool) $data['skipHeader'])
                ->execute();
        });
    }
}
