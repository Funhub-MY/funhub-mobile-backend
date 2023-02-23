<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantResource\Pages;
use App\Filament\Resources\MerchantResource\RelationManagers;
use App\Models\Merchant;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Merchant Name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),
                        Forms\Components\Select::make('user_id')
                            ->label('Belongs To User')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                            ->required()
                            ->relationship('user','name')
                    ]),
                Forms\Components\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->label('Name')
                            ->rules('required', 'max:255'),
                        Forms\Components\TextInput::make('business_phone_no')
                            ->required()
                            ->label('Phone Number')
                            ->rules('required'),
                        Forms\Components\Textarea::make('address')
                            ->required(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->required(),
                    ]),
                Forms\Components\Section::make('Person In Charge Information')
                    ->schema([
                        Forms\Components\TextInput::make('pic_name')
                            ->label('Name')
                            ->required(),
                        Forms\Components\TextInput::make('pic_phone_no')
                            ->label('Phone Number')
                            ->required(),
                        Forms\Components\TextInput::make('pic_email')
                            ->label('Email')
                            ->required()
                            ->rules('required', 'email')
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('business_name'),
                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('address_postcode'),
                Tables\Columns\TextColumn::make('pic_name'),
                Tables\Columns\TextColumn::make('pic_phone_no'),
                Tables\Columns\TextColumn::make('pic_email'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchants::route('/'),
            'create' => Pages\CreateMerchant::route('/create'),
            'edit' => Pages\EditMerchant::route('/{record}/edit'),
        ];
    }
}
