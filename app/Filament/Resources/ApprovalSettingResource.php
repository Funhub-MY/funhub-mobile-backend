<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\ApprovalSetting;
use Filament\Resources\Resource;
use Spatie\Permission\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ApprovalSettingResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\ApprovalSettingResource\RelationManagers;

class ApprovalSettingResource extends Resource
{
    protected static ?string $model = ApprovalSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Approvals';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('approvable_type')
                    ->label('Approvable Type')
                    ->options([
                        'App\Models\Reward' => 'Reward',
                        'App\Models\RewardComponent' => 'Reward Component',
                    ])
                    ->searchable()
                    ->required(),
                Fieldset::make('role_and_sequence')
                    ->schema([
                        Select::make('role_id')
                            ->label('Role')
                            ->options(Role::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->rules([
                                'required',
                                function (Closure $get) {
                                    return function (string $attribute, $value, Closure $fail) use ($get) {
                                        $roleId = $value;
                                        $approvableType = $get('approvable_type');

                                        $conflictingRole = ApprovalSetting::where('approvable_type', $approvableType)
                                            ->where('role_id', $roleId)
                                            ->count();

                                        if ($conflictingRole) {
                                            $fail('This role already exists.');
                                        }
                                    };
                                }
                            ]),
                        TextInput::make('sequence')
                            ->label('Sequence')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Enter the sequence number of the role (e.g. 1, 2, 3).
                        <br>Lower number indicates approval is required first.')
                            ->required()
                            ->rules([
                                'required',
                                function (Closure $get) {
                                    return function (string $attribute, $value, Closure $fail) use ($get) {
                                        $sequence = $value;
                                        $approvableType = $get('approvable_type');

                                        $existingSequence = ApprovalSetting::where('approvable_type', $approvableType)
                                            ->where('sequence', $sequence)
                                            ->count();

                                        $maxSequence = ApprovalSetting::where('approvable_type', $approvableType)
                                            ->max('sequence');

                                        if ($maxSequence !== null && $existingSequence > 0) {
                                            $fail('This sequence already exists.');
                                        } else if ((int)$sequence !== $maxSequence + 1) {
                                            $fail('The sequence should be exactly one more than the current maximum sequence.');
                                        }
                                    };
                                }
                            ])
                    ])
                    ->label('Role & Sequence')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('approvable_type')
                    ->label('Approvable Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label('Role'),
                TextColumn::make('sequence')
                    ->label('Sequence')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalSettings::route('/'),
            'create' => Pages\CreateApprovalSetting::route('/create'),
            'edit' => Pages\EditApprovalSetting::route('/{record}/edit'),
        ];
    }
}
