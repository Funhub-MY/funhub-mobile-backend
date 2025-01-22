<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCreditResource\Pages;
use App\Filament\Resources\ProductCreditResource\RelationManagers;
use App\Models\ProductCredit;
use App\Models\User;
use App\Filament\Resources\UserResource;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductCreditResource extends Resource
{
    protected static ?string $model = ProductCredit::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Product Credit Details')
                    ->description('Credit a product and its rewards to a user')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return User::query()
                                    ->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%")
                                    ->orWhere('phone_no', 'LIKE', "%{$search}%")
                                    ->orWhere('username', 'LIKE', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        return [
                                            $user->id => $user->name . ($user->username ? " (username: {$user->username}, ID: {$user->id})" : '')
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): string {
                                $user = User::find($value);
                                return $user ? $user->name . ($user->username ? " (username: {$user->username}, ID: {$user->id})" : '') : '';
                            })
                            ->searchable(),
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('ref_no')
                            ->label('Reference Number')
                            ->placeholder('Optional reference number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('paid_by')
                            ->label('Paid By')
                            ->placeholder('Optional payment source')
                            ->maxLength(255),
                    ])->columns(1)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn ($record): string => 
                        ($record->user->email ? $record->user->email : '') .
                        ($record->user->phone_no ? ' - ' . $record->user->phone_no : '')
                    )
                    ->url(fn ($record): string => UserResource::getUrl('view', ['record' => $record->user_id]))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->description(fn ($record): string => 
                        'Rewards: ' . 
                        ($record->product->rewards()->exists() ? 
                            $record->product->rewards->map(fn($reward) => 
                                "{$reward->name} ({$reward->pivot->quantity})"
                            )->join(', ') : 'None')
                    )
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ref_no')
                    ->label('Reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('paid_by')
                    ->label('Paid By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListProductCredits::route('/'),
            'create' => Pages\CreateProductCredit::route('/create'),
        ];
    }    
}
