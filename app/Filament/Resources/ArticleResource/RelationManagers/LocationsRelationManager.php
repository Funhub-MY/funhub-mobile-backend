<?php

namespace App\Filament\Resources\ArticleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'location';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('full_address'),
                Tables\Columns\TextColumn::make('ratings')
                ->label('Rating by Poster')
                ->getStateUsing(function (Model $record) {
                    // get article's user's iod
                    $article_id = request()->route()->parameter('record');
                    if ($article_id) {
                        $user_id = \App\Models\Article::find($article_id)->user_id;
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
                Tables\Actions\AttachAction::make()->preloadRecordSelect()
                ->form(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    TextInput::make('rating')
                        ->numeric()
                        ->nullable()
                        ->rules('min:1', 'max:5')
                        ->label('Rating')
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }    
}
