<?php

namespace App\Filament\Resources\SupportRequestResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\HtmlString;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $recordTitleAttribute = 'message';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),

                Forms\Components\TextInput::make('message')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent At')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                // show message with custom html if has image display as html link open new tab
                TextColumn::make('message')
                    ->label('Message')
                    ->getStateUsing( function (Model $record){
                        $mediaLinks = $record->getMedia('support_uploads')->map(function ($media) {
                            return '<a href="' . $media->getUrl() . '" target="_blank" style="color: blue; text-decoration: underline;">' . $media->file_name . '</a>';
                        })->filter()->implode('<br>');
                        return new HtmlString($record->message . ($mediaLinks ? '<br>' . $mediaLinks : ''));
                    })
                    // ->html()
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
