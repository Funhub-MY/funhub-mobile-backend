<?php

namespace App\Filament;

use Exception;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\MerchantCategory;
use App\Models\Store;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Konnco\FilamentImport\Actions\ImportField;
use Konnco\FilamentImport\Concerns\HasActionMutation;
use Konnco\FilamentImport\Concerns\HasActionUniqueField;
use Maatwebsite\Excel\Concerns\Importable;

class ArticleCustomImport
{
    use Importable;
    use HasActionMutation;
    use HasActionUniqueField;

    protected string $spreadsheet;

    protected Collection $fields;

    protected array $formSchemas;

    protected string|Model $model;

    protected string $disk = 'local';

    protected bool $shouldSkipHeader = false;

    protected bool $shouldMassCreate = true;

    protected bool $shouldHandleBlankRows = false;

    protected ?Closure $handleRecordCreation = null;

    public static function make(string $spreadsheetFilePath): self
    {
        return (new self)
            ->spreadsheet($spreadsheetFilePath);
    }

    public function fields(Collection $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public static function formSchemas(array $formSchemas): static
    {
        $this->formSchemas = $formSchemas;

        return $this;
    }

    public function spreadsheet($spreadsheet): static
    {
        $this->spreadsheet = $spreadsheet;

        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function disk($disk = 'local'): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function skipHeader(bool $shouldSkipHeader): static
    {
        $this->shouldSkipHeader = $shouldSkipHeader;

        return $this;
    }

    public function massCreate($shouldMassCreate = true): static
    {
        $this->shouldMassCreate = $shouldMassCreate;

        return $this;
    }

    public function handleBlankRows($shouldHandleBlankRows = false): static
    {
        $this->shouldHandleBlankRows = $shouldHandleBlankRows;

        return $this;
    }

    public function getSpreadsheetData(): Collection
    {
        $data = $this->toCollection(new UploadedFile(Storage::disk($this->disk)->path($this->spreadsheet), $this->spreadsheet))
            ->first()
            ->skip((int) $this->shouldSkipHeader);
        if (! $this->shouldHandleBlankRows) {
            return $data;
        }

        return $data->filter(function ($row) {
            return $row->filter()->isNotEmpty();
        });
    }

    public function validated($data, $rules, $customMessages, $line)
    {
        $validator = Validator::make($data, $rules, $customMessages);

        try {
            if ($validator->fails()) {
                Notification::make()
                    ->danger()
                    ->title(trans('filament-import::actions.import_failed_title'))
                    ->body(trans('filament-import::validators.message', ['line' => $line, 'error' => $validator->errors()->first()]))
                    ->persistent()
                    ->send();

                return false;
            }
        } catch (Exception $e) {
            return $data;
        }

        return $data;
    }

    public function handleRecordCreation(Closure|null $closure): static
    {
        $this->handleRecordCreation = $closure;

        return $this;
    }

    public function execute()
    {
        $importSuccess = true;
        $skipped = 0;

        $chunks = $this->getSpreadsheetData()->chunk(100);

        foreach ($chunks as $rows) {
            foreach ($rows as $line => $row) {
                $prepareData = collect([]);

                foreach (Arr::dot($this->fields) as $key => $value) {
                    $field = $this->formSchemas[$key];
                    $fieldValue = $value;

                    if ($field instanceof ImportField) {
                        if (! $field->isRequired() && blank(@$row[$value])) {
                            continue;
                        }

                        $fieldValue = $field->doMutateBeforeCreate($row[$value], collect($row)) ?? $row[$value];
                    }

                    $prepareData[$key] = $fieldValue;
                }

				$articleId = $prepareData['article_id'];
				$article = Article::find($articleId);

                if ($article) {
                    Log::info('Article found, id: ' . $article->id);
                    // update the article's status
//                    try {
//                        $status = Article::STATUS[$status];
//
//                        if (isset($status)) {
//                            $article->status = $status;
//                            $article->save();
//
//                            Log::info('Article status updated to: ' . $status, [
//                                'article_id' => $article->id,
//                                'original_status' => $article->status,
//                            ]);
//                        } else {
//                            Log::info('Article status not updated, current status: ' . $article->status, [
//                                'article_id' => $article->id,
//                                'status' => $article,
//                            ]);
//                        }
//                    } catch (\Exception $e) {
//                        Log::error('Error updating article status', [
//                            'article_id' => $article->id,
//                            'status' => $status,
//                            'error' => $e->getMessage(),
//                        ]);
//                    }

					try {
						// Clean and prepare category names
						$categoryNames = array_map(
							'trim',
							explode(',', preg_replace('/\s+/', ' ', $prepareData['category_names']))
						);

						$subCategoryNames = array_map(
							'trim',
							explode(',', preg_replace('/\s+/', ' ', $prepareData['sub_categories']))
						);

						// Get category IDs
						$mainCategories = ArticleCategory::whereIn('name', $categoryNames)
							->whereNull('parent_id')
							->pluck('id')
							->toArray();

						$subCategories = ArticleCategory::whereIn('name', $subCategoryNames)
							->whereNotNull('parent_id')
							->pluck('id')
							->toArray();

						// Combine both category types for a single sync operation
						$allCategoryIds = array_merge($mainCategories, $subCategories);

						// Perform a single sync operation with all category IDs
						DB::transaction(function () use ($article, $allCategoryIds) {
							$article->categories()->sync($allCategoryIds);
						});

						Log::info('Article categories synced successfully', [
							'article_id' => $article->id,
							'main_categories' => $mainCategories,
							'sub_categories' => $subCategories
						]);
					} catch (Exception $e) {
						Log::error('Error syncing article categories', [
							'article_id' => $article->id,
							'error' => $e->getMessage(),
						]);
					}
                } else {
                    Log::info('Article not found, id: ' . $articleId);
                }
            }
        };

        if ($importSuccess) {
            Notification::make()
                ->success()
                ->title(trans('filament-import::actions.import_succeeded_title'))
                ->body(trans('filament-import::actions.import_succeeded', ['count' => count($this->getSpreadsheetData()), 'skipped' => $skipped]))
                ->persistent()
                ->send();
        }
    }
}
