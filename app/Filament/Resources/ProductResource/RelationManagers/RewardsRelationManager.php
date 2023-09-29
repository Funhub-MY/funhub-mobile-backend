<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Reward;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\AttachAction;

class RewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\SpatieMediaLibraryFileUpload::make('thumbnail')
                    ->label('Thumbnail')
                    ->collection(Reward::COLLECTION_NAME)
                    // disk is s3_public 
                    ->disk(function () {
                        if (config('filesystems.default') === 's3') {
                            return 's3_public';
                        }
                    })
                    ->acceptedFileTypes(['image/*'])
                    ->maxFiles(1)
                    ->rules('image'),

                TextInput::make('name')
                    ->required(),

                // points double
                TextInput::make('points')
                    ->label('Single Value')
                    ->rules('required', 'numeric')
                    ->required(),
                
                Textarea::make('description')
                    ->required(),

                // user id auto fill hidden
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('name')
            ->searchable()
            ->sortable(),

            TextColumn::make('description')
                ->searchable()
                ->sortable(),

            TextColumn::make('points')
                ->label('Single Value')
                ->searchable()
                ->sortable(),

            TextColumn::make('quantity')
                ->searchable()
                ->sortable(),
        ])
            ->filters([
                //
            ])
            ->headerActions([
                //Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()->preloadRecordSelect()
                ->form(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->rules('min:1')
                        ->label('Quantity')
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }    
}
