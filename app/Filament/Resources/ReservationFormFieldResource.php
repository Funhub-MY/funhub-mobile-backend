<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationFormFieldResource\Pages;
use App\Filament\Resources\ReservationFormFieldResource\RelationManagers;
use App\Models\ReservationFormField;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ReservationFormFieldResource extends Resource
{
    protected static ?string $model = ReservationFormField::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Events';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Reservation Form Fields';

    protected static ?string $modelLabel = 'Reservation Form Field';

    protected static ?string $pluralModelLabel = 'Reservation Form Fields';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Reservation Form Field Details')
                    ->schema([
                        Select::make('campaign_id')
                            ->label('Campaign')
                            ->relationship('campaign', 'title')
                            ->searchable()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Select the campaign for this reservation form configuration'),
                        
                        Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true)
                            ->required(),
                    ]),
                
                Section::make('Form Fields')
                    ->description('Define the form fields that will be displayed in the mobile app for reservations. All form data will be stored directly in the form_data JSON column using field_key as the key. No database column mapping needed.')
                    ->schema([
                        Forms\Components\Textarea::make('form_fields')
                            ->label('Form Fields JSON')
                            ->required()
                            ->helperText('Enter form fields as JSON array. All submitted data will be stored in form_data JSON using field_key as the key. Options for select/checkbox fields are handled by mobile app. Example: [{"field_key": "name", "label": "Full Name", "field_type": "text"}, {"field_key": "email", "label": "Email", "field_type": "email"}, {"field_key": "city", "label": "City", "field_type": "select"}]')
                            ->rows(20)
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                                if (is_array($state)) {
                                    $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                } elseif (is_string($state) && !empty($state)) {
                                    // Try to pretty-print if it's a valid JSON string
                                    $decoded = json_decode($state, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $component->state(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_string($state) && !empty($state)) {
                                    $decoded = json_decode($state, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        return $decoded;
                                    }
                                }
                                return is_array($state) ? $state : [];
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('form_fields_count')
                    ->label('Form Fields Count')
                    ->getStateUsing(function ($record) {
                        $formFields = $record->form_fields;
                        if (is_array($formFields)) {
                            return count($formFields);
                        }
                        if (is_string($formFields)) {
                            $decoded = json_decode($formFields, true);
                            return is_array($decoded) ? count($decoded) : 0;
                        }
                        return 0;
                    })
                    ->alignCenter(),
                
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'title')
                    ->searchable(),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListReservationFormFields::route('/'),
            'create' => Pages\CreateReservationFormField::route('/create'),
            'edit' => Pages\EditReservationFormField::route('/{record}/edit'),
            'view' => Pages\ViewReservationFormField::route('/{record}'),
        ];
    }    
}

