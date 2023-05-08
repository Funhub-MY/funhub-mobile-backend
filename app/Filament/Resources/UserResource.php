<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),
                        
                        // username
                        Forms\Components\TextInput::make('username')
                            ->required()
                            // transform lowercaser and remove spaces
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state(Str::slug($state));
                            })
                            ->rules('required', 'max:255', 'unique:users,username'),

                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->rules('required', 'email', 'max:255', 'unique:users,email'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->rules( 'min:8', 'max:255'),

                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->rules('nullable', 'date'),

                        // inline phone_country_code and phone_no without labels but placeholders textinputs
                        Forms\Components\Fieldset::make('Phone Number')
                            ->schema([
                                Forms\Components\TextInput::make('phone_country_code')
                                    ->placeholder('60')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 1]),
                                Forms\Components\TextInput::make('phone_no')
                                    ->placeholder('eg. 123456789')
                                    ->label('')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('nullable', 'max:255')->columnSpan(['lg' => 3]),
                            ])->columns(4),

                        // otp_verified_at
                        Forms\Components\DateTimePicker::make('otp_verified_at')
                            ->label('Phone No OTP Verified At')
                            ->helperText('If user OTP is not verified they cannot login to App')
                            ->rules('date'),

                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        // avatar
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->maxFiles(1)
                            ->nullable()
                             // disk is s3_public 
                             ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->collection('avatar'),

                        // bio
                        Forms\Components\Textarea::make('bio')
                            ->rules('nullable', 'max:255'),

                        // date of birth
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->rules('nullable'),

                        // gender radio
                        Forms\Components\Radio::make('gender')
                            ->inline()
                            ->options([
                              'male' => 'Male', 
                              'female' => 'Female'  
                            ])
                            ->rules('nullable'),

                        // job_title
                        Forms\Components\TextInput::make('job_title')
                            ->rules('nullable', 'max:255'),

                        // field set location
                        Forms\Components\Fieldset::make('Location')
                            ->schema([
                                // country relationship select searcheable nullable
                                Forms\Components\Select::make('country_id')
                                    ->label('Country')
                                    ->relationship('country', 'name')
                                    ->preload()
                                    ->nullable()
                                    ->rules('nullable'),

                                // state relationship select searcheable nullable
                                Forms\Components\Select::make('state_id')
                                    ->label('State')
                                    ->relationship('state', 'name')
                                    ->preload()
                                    ->nullable()
                                    ->rules('nullable'),
                            ])
                        
                    ])->columnSpan(['lg' => 2]),
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(name: 'name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('full_phone_no')->label('Phone No'),
                Tables\Columns\TextColumn::make(name: 'email')->sortable()->searchable(),
                Tables\Columns\TextColumn::make(name: 'email_verified_at')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()
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
            RelationManagers\RolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
