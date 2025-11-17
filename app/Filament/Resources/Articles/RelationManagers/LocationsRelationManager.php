<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use App\Models\Article;
use Filament\Actions\AttachAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'location';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('full_address'),
                TextColumn::make('ratings')
                ->label('Rating by Poster')
                ->getStateUsing(function (Model $record) {
                    // get article's user's iod
                    $article_id = request()->route()->parameter('record');
                    if ($article_id) {
                        $user_id = Article::find($article_id)->user_id;
                        return $record->ratings()
                            ->where('user_id', $user_id)->first()->rating ?? 0;
                    } else {
                        return 0;
                    }
                })

            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()->preloadRecordSelect()
                ->form(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    TextInput::make('rating')
                        ->numeric()
                        ->nullable()
                        ->rules('min:1', 'max:5')
                        ->label('Rating')
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }    
}
