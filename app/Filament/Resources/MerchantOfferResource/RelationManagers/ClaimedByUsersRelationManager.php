<?php

namespace App\Filament\Resources\MerchantOfferResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClaimedByUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'user_id';

    protected static ?string $modelLabel = 'Claimed By Users';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
/*                Forms\Components\Select::make('claimed_by_users')
                    ->label('Claimed By Users')
                    ->relationship('user', 'name')
                    ->multiple()
                    ->preload(),*/
/*                Forms\Components\Select::make('users')
                    ->label('Claimed by User')
                    ->preload()
                    ->searchable()
                    ->relationship('users', 'name'),*/
                Forms\Components\Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->relationship('merchant_offers', 'id'),


                Forms\Components\Select::make('status')
                    ->options([
                        1 => 'Claimed',
                        2 => 'Failed',
                        3 => 'Await Payment'
                    ])
                    ->default(1)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        1 => 'Claimed',
                        2 => 'Failed',
                        3 => 'Await Payment'
                    ])
                    ->colors([
                        'success' => static fn ($state): bool => $state === 1,
                        'danger' => static fn ($state): bool => $state === 2,
                    ]),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
/*                Tables\Actions\AttachAction::make()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('status')
                            ->options([
                                1 => 'Claimed',
                                2 => 'Revoked'
                            ])
                            ->default(1)
                            ->required(),
                    ]),*/
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
