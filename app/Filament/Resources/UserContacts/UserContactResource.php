<?php

namespace App\Filament\Resources\UserContacts;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserContacts\Pages\ListUserContacts;
use App\Filament\Resources\UserContacts\Pages\CreateUserContact;
use App\Filament\Resources\UserContacts\Pages\EditUserContact;
use App\Filament\Resources\UserContactResource\Pages;
use App\Filament\Resources\UserContactResource\RelationManagers;
use App\Models\User;
use App\Models\UserContact;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class UserContactResource extends Resource
{
    protected static ?string $model = UserContact::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('Imported by user')
                        ->relationship('importedByUser', 'name')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            if ($record->name) {
                                return $record->name;
                            } else if ($record->full_phone_no) {
                                return $record->full_phone_no;
                            } else if ($record->email) {
                                return $record->email;
                            } else {
                                return 'Unknown';
                            }
                        })
                        ->searchable()
                        ->required(),

                    Select::make('Related user')
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            if ($record->name) {
                                return $record->name;
                            } else if ($record->full_phone_no) {
                                return $record->full_phone_no;
                            } else if ($record->email) {
                                return $record->email;
                            } else {
                                return 'Unknown';
                            }
                        })
                        ->nullable()
                        ->searchable()
                        ->relationship('relatedUser', 'name'),

                    Fieldset::make('Phone Number')
                        ->schema([
                            TextInput::make('phone_country_code')
                                ->placeholder('60')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                })
                                ->rules('nullable', 'max:255')->columnSpan(['lg' => 1]),
                            TextInput::make('phone_no')
                                ->placeholder('eg. 123456789')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                })
                                ->rules('nullable', 'max:255')->columnSpan(['lg' => 3]),
                        ])->columns(4),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('importedByUser.name')
                    ->label('Imported by user')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('relatedUser.name')
                    ->label('Related user')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_country_code')
                    ->label('Phone Country Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_no')
                    ->label('Phone No')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
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
								Column::make('id')->heading('Contact Id'),
								Column::make('imported_by_id')->heading('By User Id'),
								Column::make('importedByUser.name')->heading('By User Name'),
								Column::make('name')->heading('Name'),
								Column::make('phone_country_code')->heading('Phone Country Code'),
								Column::make('phone_no')->heading('Phone No'),
								Column::make('related_user_id')->heading('Related User Id'),
								Column::make('relatedUser.name')->heading('Related User Name'),
								Column::make('created_at')
									->heading('Created At')
									->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
							])
							->withChunkSize(500)
							->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
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

    public static function getPages(): array
    {
        return [
            'index' => ListUserContacts::route('/'),
            'create' => CreateUserContact::route('/create'),
            'edit' => EditUserContact::route('/{record}/edit'),
        ];
    }
}
