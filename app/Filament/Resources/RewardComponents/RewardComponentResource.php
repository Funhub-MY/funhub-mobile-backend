<?php

namespace App\Filament\Resources\RewardComponents;

use Filament\Schemas\Schema;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RewardComponents\Pages\ListRewardComponents;
use App\Filament\Resources\RewardComponents\Pages\CreateRewardComponent;
use App\Filament\Resources\RewardComponents\Pages\EditRewardComponent;
use Closure;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\RewardComponentResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\RewardComponentResource\RelationManagers;
use App\Filament\Resources\RewardComponents\RelationManagers\RewardsRelationManager;

class RewardComponentResource extends Resource
{
    protected static ?string $model = RewardComponent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Points & Rewards';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                Select::make('reward')
                    ->relationship('rewards', 'name')
                    ->multiple()
                    ->required(),
                
                SpatieMediaLibraryFileUpload::make('thumbnail')
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
                TextInput::make('name')
                    ->label('Name')
                    ->autofocus()
                    ->required()
                    ->rules('required', 'max:255'),

                // description
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->rules('required', 'max:255'),

                // user id auto fill hidden
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // name
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                // description
                TextColumn::make('description')
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
            'index' => ListRewardComponents::route('/'),
            'create' => CreateRewardComponent::route('/create'),
            'edit' => EditRewardComponent::route('/{record}/edit'),
        ];
    }
}
