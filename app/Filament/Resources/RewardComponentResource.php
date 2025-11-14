<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RewardComponentResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\RewardComponentResource\RelationManagers;
use App\Filament\Resources\RewardComponentResource\RelationManagers\RewardsRelationManager;

class RewardComponentResource extends Resource
{
    protected static ?string $model = RewardComponent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Points & Rewards';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                
                Select::make('reward')
                    ->relationship('rewards', 'name')
                    ->multiple()
                    ->required(),
                
                Forms\Components\SpatieMediaLibraryFileUpload::make('thumbnail')
                    ->label('Thumbnail')
                    ->collection(RewardComponent::COLLECTION_NAME)
                    // disk is s3_public 
                    ->disk(function () {
                        if (config('filesystems.default') === 's3') {
                            return 's3_public';
                        }
                    })
                    ->acceptedFileTypes(['image/*'])
                    ->maxFiles(1)
                    ->rules('image'),

                // name
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->autofocus()
                    ->required()
                    ->rules('required', 'max:255'),

                // description
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->rules('required', 'max:255'),

                // user id auto fill hidden
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // name
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                // description
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable(),
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
            RewardsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardComponents::route('/'),
            'create' => Pages\CreateRewardComponent::route('/create'),
            'edit' => Pages\EditRewardComponent::route('/{record}/edit'),
        ];
    }
}
