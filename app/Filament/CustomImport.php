<?php

namespace App\Filament;

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

class CustomImport
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

    public function formSchemas(array $formSchemas): static
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
        } catch (\Exception $e) {
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

                $storeId = $prepareData['store_id'];

                // ensure category_names dont have space between category names
                $categoryNames = explode(',', preg_replace('/\s+/', ' ', $prepareData['category_names']));
                // $categoryNames = explode(',', $prepareData['category_names']);
                $status = $prepareData['status'];

                $store = Store::find($storeId);

                if ($store) {
                    Log::info('Store found, id: ' . $store->id);
                    // update the store's status
                    try {
                        $status = Store::STATUS_LABEL[$status];

                        if (isset($status)) {
                            $store->status = $status;
                            $store->save();

                            Log::info('Store status updated to: ' . $status, [
                                'store_id' => $store->id,
                                'original_status' => $store->status,
                            ]);
                        } else {
                            Log::info('Store status not updated, current status: ' . $store->status, [
                                'store_id' => $store->id,
                                'status' => $status,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error updating store status', [
                            'store_id' => $store->id,
                            'status' => $status,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // get all category ids
                    // trim each category name
                    try {
                        $categoryNames = array_map(function ($categoryName) {
                            return trim($categoryName);
                        }, $categoryNames);

                        $categoryIds = MerchantCategory::whereIn('name', $categoryNames)->pluck('id')->toArray();

                        if (count($categoryIds) > 0) {
                            // sync store categories
                            $store->categories()->sync($categoryIds);
                            Log::info('Store category synced, store id: ' . $store->id . ' category ids: ' . implode(',', $categoryIds));
                        } else {
                            // detach all
                            $store->categories()->detach();
                        }
                    } catch (\Exception $e) {
                        Log::error('Error syncing store categories', [
                            'store_id' => $store->id,
                            'category_names' => $categoryNames,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::info('Store not found, id: ' . $storeId);
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
