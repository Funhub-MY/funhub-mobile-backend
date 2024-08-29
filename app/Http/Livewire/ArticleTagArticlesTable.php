<?php

namespace App\Http\Livewire;

use App\Models\ArticleTag;
use Filament\Tables\Filters\Filter;
use Livewire\Component;
use App\Models\Article;
use App\Models\ArticleTagsArticlesCount;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Response;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleTagArticlesTable extends Component implements HasTable
{
    use InteractsWithTable;

    public $currentTagId = null;

    public function render()
    {
        return view('livewire.article-tag-articles-table');
    }

    public function mount()
    {
        $current_record = Route::current()->parameters('record');

        if (isset($current_record['record'])) {
            $this->currentTagId = $current_record['record'];
        }
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('title')
                ->label('Title')
                ->sortable()
                ->url(fn ($record) => route('filament.resources.articles.edit', $record->id))
                ->searchable()
                ->limit(50),
            TextColumn::make('user.name')->label('Created By')
                ->limit(30)
                ->url(fn ($record) => route('filament.resources.users.view', $record->user))
                ->openUrlInNewTab()
                ->sortable()
                ->searchable(),
            TextColumn::make('status')
                ->label('Status')
                ->enum(Article::STATUS)
                ->sortable()
                ->searchable(),
            TextColumn::make('created_at')
                ->label('Created At')
                ->dateTime('d/m/Y H:i:s')
                ->sortable()
                ->searchable(),
            TextColumn::make('published_at')
                ->label('Published At')
                ->dateTime('d/m/Y H:i:s')
                ->sortable()
                ->searchable(),
            TextColumn::make('likes_count')
                ->label('Likes')
                ->counts('likes')
                ->sortable(),
            TextColumn::make('bookmarks_count')
                ->label('Bookmarks')
                ->counts('bookmarks')
                ->sortable(),
            TextColumn::make('shares_count')
                ->label('Shares')
                ->counts('shares')
                ->sortable(),
            TextColumn::make('comments_count')
                ->label('Comments')
                ->counts('comments')
                ->sortable(),
            TextColumn::make('views_count')
                ->label('Views')
                ->counts('views')
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            // filter by status
            SelectFilter::make('status')
                ->label('Status')
                ->options(Article::STATUS),
            Filter::make('created_from')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('created_from')
                        ->placeholder('Select start date'),
                ])
                ->query(function (Builder $query, array $data) {
                    if ($data['created_from']) {
                        $query->whereDate('created_at', '>=', $data['created_from']);
                    }
                })
                ->label('Created From'),
            Filter::make('created_until')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('created_until')
                        ->placeholder('Select end date'),
                ])
                ->query(function (Builder $query, array $data) {
                    if ($data['created_until']) {
                        $query->whereDate('created_at', '<=', $data['created_until']);
                    }
                })
                ->label('Created Until'),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            ExportBulkAction::make()->exports([
                ExcelExport::make('table')
                    ->fromTable()
                    ->withFilename(fn () => 'articles_tags_' . date('Y-m-d'))
                    ->withColumns(['id'])
            ]),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentTagId) {

            $tagName = ArticleTagsArticlesCount::find($this->currentTagId)->name;
            // find articletags with same name
            $articleTags = ArticleTag::where('name', $tagName)->with('articles')
                ->get();

            // get article ids from articletags
            $articleIds = $articleTags->pluck('articles')->map(function ($articleTag) {
                return $articleTag->pluck('id')->toArray();
            })->flatten()->toArray();

            // get articles with article ids
            return Article::whereIn('id', $articleIds)->with('user');
        }

        return Article::query();
    }
}
