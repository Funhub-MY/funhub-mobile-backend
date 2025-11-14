<?php

namespace App\Filament\Resources\SystemNotificationResource\RelationManagers;

use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Filament\Tables\Actions\Action;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SystemNotificationUsersRelationManager extends RelationManager
{
	protected $listeners = ['refreshRelation' => '$refresh'];

    protected static string $relationship = 'users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
				TextColumn::make('id')
					->searchable()
					->label('User ID'),
				TextColumn::make('username')
					->searchable()
					->label('Username'),
			])
            ->filters([
                //
            ])
			->headerActions([
				Action::make('import')
					->form([
						FileUpload::make('csv_file')
							->label('File')
							->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
							->helperText('File should contain "user_id" and "username" columns.')
							->disk('public'),
					])
					->action(function (RelationManager $livewire, array $data) {
						$path = Storage::disk('public')->path($data['csv_file']);

						// Load the spreadsheet
						$spreadsheet = IOFactory::load($path);
						$worksheet = $spreadsheet->getActiveSheet();

						$processedUsers = 0;
						$errors = [];
						$importedUserIds = [];

						// Get the highest column and row
						$highestColumn = $worksheet->getHighestColumn();
						$highestRow = $worksheet->getHighestRow();

						// Find user_id column
						$userIdColumn = null;
						foreach (range('A', $highestColumn) as $column) {
							$cellValue = $worksheet->getCell($column . '1')->getValue();
							if (strtolower($cellValue) === 'user_id') {
								$userIdColumn = $column;
								break;
							}
						}

						if (!$userIdColumn) {
							throw new \Exception('user_id column not found in file');
						}

						// Access the ownerRecord using the livewire instance
						$notification = $livewire->getOwnerRecord();

						// Begin transaction
						DB::beginTransaction();
						try {
							// Get current user IDs from the notifications table
							$currentUserIds = json_decode($notification->user ?? '[]', true);
							if (!is_array($currentUserIds)) {
								$currentUserIds = [];
							}

							// Process rows starting from row 2
							for ($row = 2; $row <= $highestRow; $row++) {
								$userId = $worksheet->getCell($userIdColumn . $row)->getValue();

								if (!empty($userId)) {
									// Convert to integer
									$userId = (int) $userId;

									// Collect imported user IDs
									$importedUserIds[] = $userId;
									$processedUsers++;
								}
							}

							$notification->users()->syncWithoutDetaching($importedUserIds);

//							// Merge current and imported user IDs, remove duplicates
//							$allUserIds = array_values(array_unique(array_merge($currentUserIds, $importedUserIds)));
//
//							// Update the notification record
//							$notification->update([
//								'user' => json_encode($allUserIds)
//							]);

							DB::commit();
						} catch (\Exception $e) {
							DB::rollback();
							$errors[] = "Error during import: " . $e->getMessage();
						}

						// Clean up the temporary file
						Storage::disk('public')->delete($data['csv_file']);

						// Show success/error message
						$message = "Processed {$processedUsers} users successfully.";
						if (count($errors) > 0) {
							$message .= "\nErrors encountered: " . implode("\n", $errors);
						}

						Notification::make()
							->success()
							->title('Import Complete')
							->body($message)
							->send();
					})
					->hidden(fn ($livewire) =>
						$livewire->ownerRecord->selection_type === 'select' ||
						$livewire->ownerRecord->all_active_users === 1
					)
			])
			->bulkActions([
				Tables\Actions\DetachBulkAction::make(),
			]);
    }    
}
