<?php

namespace App\Filament\Resources\MerchantOffers\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClaimedByUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'user_id';

    protected static ?string $modelLabel = 'Claimed By Users';


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->relationship('merchant_offers', 'id'),


                Select::make('status')
                    ->options([
                        1 => 'Claimed',
                        2 => 'Failed',
                        3 => 'Await Payment'
                    ])
                    ->default(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id'),
                TextColumn::make('name'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        1 => 'Claimed',
                        2 => 'Failed',
                        3 => 'Await Payment',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        1 => 'success',
                        2 => 'danger',
                        default => 'gray',
                    }),
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
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
