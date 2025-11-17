<?php

namespace App\Filament\Resources\Referrals;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Filament\Resources\Referrals\Pages\CreateReferral;
use App\Filament\Resources\Referrals\Pages\EditReferral;
use App\Filament\Resources\ReferralResource\Pages;
use App\Filament\Resources\ReferralResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ReferralResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'referrals';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->sortable(query: function (Builder $query, $direction) {
                        $query->leftJoin('users as ref', 'ref.referred_by_id', '=', 'users.id')
                            ->selectRaw('users.*, COUNT(ref.id) as referral_total_number')
                            ->groupBy('users.id')
                            ->orderBy('referral_total_number', $direction);
                    })
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
            ->recordActions([
                EditAction::make(),
            ])
			->toolbarActions([
				DeleteBulkAction::make(),
				ExportBulkAction::make()
					->exports([
						ExcelExport::make()
							->withColumns([
								Column::make('id')->heading('User Id'),
								Column::make('username')->heading('User'),
								Column::make('referral_total_number')
									->heading('Referral Total Number')
									->getStateUsing(function (User $record) {
										return $record->referrals()->count();
									}),
								Column::make('total_funbox_get')
									->heading('Total Funbox Get')
									->getStateUsing(function (User $record) {
										$latestLedger = $record->pointLedgers()->orderBy('id', 'desc')->first();
										return $latestLedger ? $latestLedger->balance : 0;
									}),
								Column::make('created_at')
									->heading('Created At')
									->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
								Column::make('updated_at')
									->heading('Updated At')
									->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
							])
							->withChunkSize(500)
							->withFilename(fn ($resource) => 'referrals-' . date('Y-m-d'))
							->withWriterType(Excel::CSV),
					])
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
            'index' => ListReferrals::route('/'),
            'create' => CreateReferral::route('/create'),
            'edit' => EditReferral::route('/{record}/edit'),
        ];
    }
}
