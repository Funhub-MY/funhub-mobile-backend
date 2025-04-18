<?php

namespace App\Filament\Resources\MerchantOfferCampaignResource\RelationManagers;

use App\Models\MerchantOfferCampaignVoucherCode;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use App\Services\MerchantOfferCampaignCodeImporter;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use App\Models\MerchantOfferCampaign;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\MerchantOfferVoucher;

class VoucherCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'voucherCodes';

    protected static ?string $modelLabel = 'Imported Voucher Codes';

    protected static ?string $pluralModelLabel = 'Imported Voucher Codes';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_used')
                    ->label('Is Used')
                    ->default(false)
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_used')
                    ->label('Is Linked with Voucher')
                    ->boolean()
                    ->sortable(),
                //linked voucher
                TextColumn::make('voucher.code'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('importCodes')
                    ->label('Import Codes')
                    ->icon('heroicon-o-upload')
                    ->form([
                        FileUpload::make('attachment')
                            ->label('Voucher Codes CSV')
                            ->required()
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->disk('local') // Store temporarily locally
                            ->directory('filament-imports')
                            ->helperText('Upload a CSV file with a single column containing voucher codes. No header row.')
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        /** @var MerchantOfferCampaign $campaign */
                        $campaign = $livewire->ownerRecord;
                        $relativePath = $data['attachment'] ?? null;
                        $diskName = 'local'; // As specified in FileUpload
                        $disk = Storage::disk($diskName);

                        // Check if we have a relative path and the file exists on the disk
                        if (!$relativePath || !is_string($relativePath) || !$disk->exists($relativePath)) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body('Uploaded file not found or invalid path.')
                                ->danger()
                                ->send();
                            // Log details for debugging
                            \Log::error('Voucher Import Failed: Invalid path or file not found.', [
                                'relativePath' => $relativePath,
                                'disk' => $diskName,
                                'exists' => $relativePath ? $disk->exists($relativePath) : false
                            ]);
                            return;
                        }

                        $fullPath = $disk->path($relativePath);

                        try {
                            // Parse the CSV using the full path obtained from Storage
                            $codes = Excel::toCollection(new class { public function collection(Collection $rows) { return $rows; } }, $fullPath)
                                ->flatten() // Flatten rows and potential nested arrays
                                ->map(fn ($item) => trim(strval($item))) // Trim whitespace and ensure string
                                ->filter() // Remove empty rows/codes
                                ->unique() // Ensure unique codes
                                ->values(); // Re-index the collection

                            $importedCodeCount = $codes->count();
                            $expectedCodeCount = $campaign->agreement_quantity;

                            // Validation: Check if count matches agreement_quantity
                            if ($importedCodeCount !== $expectedCodeCount) {
                                Notification::make()
                                    ->title('Import Failed: Quantity Mismatch')
                                    ->body("Expected {$expectedCodeCount} codes based on campaign quantity, but found {$importedCodeCount} unique codes in the uploaded file.")
                                    ->danger()
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            // Use the importer service
                            $importer = new MerchantOfferCampaignCodeImporter();

                            // Prepare data for bulk insert
                            $codesToInsert = $codes->map(function ($code) use ($campaign) { // Use map, not mapWithKeys
                                return [
                                    'code' => $code,
                                    'merchant_offer_campaign_id' => $campaign->id,
                                    'is_used' => false,
                                    // Add created_at/updated_at because ::insert bypasses Eloquent mutators/timestamps
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            })->all(); // Convert collection to plain array

                            // Insert all tracking codes first
                            MerchantOfferCampaignVoucherCode::insert($codesToInsert);

                            // map to the vouchers available
                            // Now, call the importer to map them to actual vouchers
                            $importer->importCodes($campaign, $codes->all());

                            Notification::make()
                                ->title('Import Successful')
                                ->body("Successfully imported {$importedCodeCount} voucher codes.")
                                ->success()
                                ->send();

                            // Optionally refresh the table
                            $livewire->emit('refreshRelationManagerList', static::class);

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Import Failed')
                                ->body('An error occurred during import: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        } finally {
                            // Clean up the temporary file using the Storage facade and relative path
                            if (isset($relativePath) && $disk->exists($relativePath)) {
                                $disk->delete($relativePath);
                            }
                        }
                    }),
            ])
            ->actions([
               
            ])
            ->bulkActions([
                // Tables\Actions\DetachBulkAction::make(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
