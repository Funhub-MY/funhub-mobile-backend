<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Sales';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
               Group::make()->schema([
                    Section::make('Transaction')
                        ->schema([
                            TextInput::make('transaction_no')
                                ->required(),
                            Select::make('status')
                                ->options(Transaction::STATUS)
                                ->required(),
                            TextInput::make('amount')
                                ->numeric()
                                ->required(),
                            Select::make('gateway')
                                ->options([
                                    'MPAY' => 'MPAY',
                                ])
                                ->required(),
                            TextInput::make('gateway_transaction_id'),
                            TextInput::make('payment_method'),
                            TextInput::make('bank'),
                            TextInput::make('card_last_four')
                                ->numeric(),
                            TextInput::make('card_type'),
                        ])
               ])->columnSpan(['lg' => 2]),

               Group::make()->schema([
                    Section::make('Related')
                        ->schema([
                            Select::make('user_id')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->required(),

                            Select::make('product_id')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->required(),
                        ])
               ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('transaction_no')
                    ->sortable()
                    ->searchable()
                    ->label('Transaction No'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum(Transaction::STATUS)
                    ->colors([
                        'secondary' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->sortable()
                    ->searchable()
                    ->label('User'),
                TextColumn::make('transactionable.name'),
                TextColumn::make('amount')
                    ->label('Amount (RM)'),
                TextColumn::make('payment_method')
                    ->label('Payment Method'),
                
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }    
}
