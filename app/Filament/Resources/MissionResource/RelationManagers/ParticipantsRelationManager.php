<?php

namespace App\Filament\Resources\MissionResource\RelationManagers;

use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\Facades\DB;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $recordTitleAttribute = 'user_id';

	protected function getTableQuery(): Builder
	{
		$query = parent::getTableQuery();

		// Dont show deleted accounts records
		$query = $query->whereDoesntHave('userAccountDeletion');

		// Get the latest record for each user based on updated_at or completed_at
		$latestUserRecords = DB::table('missions_users')
			->select('user_id', DB::raw('MAX(id) as latest_id'))
			->where('mission_id', $this->ownerRecord->id)
			->groupBy('user_id');

		// Only get latest records
		$query->joinSub($latestUserRecords, 'latest_records', function ($join) {
			$join->on('missions_users.id', '=', 'latest_records.latest_id')
				->on('users.id', '=', 'latest_records.user_id');
		});

		return $query;
	}

	public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->sortable()
                    ->searchable(),
				Tables\Columns\TextColumn::make('name')
					->label('Name')
					->sortable()
					->searchable(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Funhub ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('missionsParticipating')
                    ->label('Progress')
                    ->sortable(['missions_users.is_completed'])
                    ->formatStateUsing(function (Model $record, $state) {
                        if ($record->is_completed) {
                            return 'Completed';
                        } else {
                            $currentMissionUserId = $record->pivot->id;

                            //Get record of current mission_user
                            $currentMissionUser = $state->filter(function ($state) use ($currentMissionUserId) {
                                return $state->pivot->id == $currentMissionUserId ;
                            });

                            $currentMissionUserArray = array_values(json_decode($currentMissionUser, true));

                            // Get total event value of this mission
                            $missionEventValues = array_map('intval', $currentMissionUserArray[0]['values']);
                            $totalMissionEventValue = array_sum($missionEventValues);

                            //Get total current event value of this mission
                            $currentEventValues = array_map('intval', json_decode($currentMissionUserArray[0]['pivot']['current_values'], true));
                            $totalCurrentEventValue = array_sum($currentEventValues);

                            return $totalCurrentEventValue . '/' . $totalMissionEventValue;
                        }
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Action At')
                    ->sortable(['missions_users.completed_at']),
            ])
            ->filters([
                Filter::make('progress')
                    ->form([
                        Select::make('progress')
                            ->options([
                                'completed' => 'Completed',
                                'ongoing' => 'Ongoing',
                            ])
                            ->placeholder('Select progress status'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['progress'])) {
                            $progress = $data['progress'] === 'completed' ? 1 : 0;
//                            $query->whereHas('participants', function (Builder $query) use ($progress) {
                                $query->where('is_completed', $progress);
//                            });
                        }
                    })
                    ->label('Progress'),
                Filter::make('completed_from')
                    ->form([
                        DatePicker::make('completed_from')
                            ->placeholder('Select start date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['completed_from']) {
                            // Specify the table name for created_at
							$query->whereDate('missions_users.completed_at', '>=', $data['completed_from']);
                        }
                    })
                    ->label('Actioned From'),

                Filter::make('completed_until')
                    ->form([
                        DatePicker::make('completed_until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['completed_until']) {
                            // Specify the table name for created_at
                            $query->whereDate('missions_users.completed_at', '<=', $data['completed_until']);
                        }
                    })
                    ->label('Actioned Until'),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->label('Export Participants')
							->fromTable()
							->only([
								'user_id',
								'username',
								'completed_at',
							])
							->withColumns([
								Column::make('user_id')
									->heading('User ID'),
								Column::make('username')
									->heading('Funhub ID'),
								Column::make('completed_at')
									->heading('Completed At'),
							])
                            ->withFilename(fn () => 'Participants-' . date('Y-m-d'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                    ]),
            ]);
    }
}
