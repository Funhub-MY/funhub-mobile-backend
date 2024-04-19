<?php

namespace App\Filament\Resources\MissionResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $recordTitleAttribute = 'user_id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(['username', 'name']),
                Tables\Columns\TextColumn::make('missionsParticipating')
                    ->label('Progress')
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
                        })
            ])
            ->filters([
                //
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
            ]);
    }
}
