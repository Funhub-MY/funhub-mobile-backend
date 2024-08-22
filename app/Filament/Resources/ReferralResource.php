<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralResource\Pages;
use App\Filament\Resources\ReferralResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReferralResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'referrals';

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('id')
                    ->label('User ID'),

                TextInput::make('username')
                    ->label('User'),

                TextInput::make('referral_total_number')
                    ->label('Referral Total Number')
                    ->afterStateHydrated(function (User $record) {
                        return $record->referrals()->count();
                    }),

                TextInput::make('total_funbox_get')
                    ->label('Total Funbox Get')
                    ->afterStateHydrated(function (User $record) {
                        $latestLedger = $record->pointLedgers()->orderBy('id', 'desc')->first();
                        return $latestLedger ? $latestLedger->balance : 0;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('User ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('username')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('referral_total_number')
                    ->label('Referral Total Number')
                    ->getStateUsing(function (User $record) {
                        return $record->referrals()->count();
                    }),
                TextColumn::make('total_funbox_get')
                    ->label('Total Funbox Get')
                    ->getStateUsing(function (User $record) {
                        $latestLedger = $record->pointLedgers()->orderBy('id', 'desc')->first();
                        return $latestLedger ? $latestLedger->balance : 0;
                    })
            ])
            ->filters([

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
            //
        ];
    }
    public static function getNavigationLabel(): string
    {
        return 'Referrals';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Users';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
            'create' => Pages\CreateReferral::route('/create'),
            'edit' => Pages\EditReferral::route('/{record}/edit'),
        ];
    }
}
