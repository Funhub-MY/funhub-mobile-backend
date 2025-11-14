<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use App\Models\Interaction;
use Filament\Forms\Form;
use App\Models\MerchantOffer;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\InteractionResource\Pages;
use App\Filament\Resources\InteractionResource\RelationManagers;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class InteractionResource extends Resource
{
    protected static ?string $model = Interaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->options(Interaction::STATUS)
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ])
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->relationship('user','name'),
                Forms\Components\MorphToSelect::make('interactable')
                    ->types([
                        // TODO:: at the moment there is only 1 type.
                        Forms\Components\MorphToSelect\Type::make(Article::class)->titleColumnName('title'),
                        Forms\Components\MorphToSelect\Type::make(MerchantOffer::class)->titleColumnName('name'),
                    ])
                    ->label('Type'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('By User'),
                Tables\Columns\TextColumn::make('type')
                    ->enum([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ]),
                Tables\Columns\TextColumn::make('meta'),
                Tables\Columns\BadgeColumn::make('status')
                    ->enum([
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Hidden',
                    ])
                    ->colors([
                        'primary' => static fn ($state): bool => $state === 0,
                        'success' => static fn ($state): bool => $state === 1,
                        'danger' => static fn ($state): bool => $state === 2,
                    ]),
                Tables\Columns\TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
				ExportBulkAction::make()
					->exports([
						ExcelExport::make()
							->withColumns([
								Column::make('id')->heading('Interaction Id'),
								Column::make('user_id')->heading('User Id'),
								Column::make('user.name')->heading('By User'),
								Column::make('interactable_type')
									->heading('Interactable Type')
									->formatStateUsing(function ($state) {
										return class_basename($state);
									}),
								Column::make('interactable_id')
									->heading('Interactable Id'),
								Column::make('type')
									->heading('Type')
									->formatStateUsing(fn ($record) => [
										1 => 'Like',
										2 => 'Dislike',
										3 => 'Share',
										4 => 'Bookmark',
									][$record->type] ?? ''),
								Column::make('status')
									->heading('Status')
									->formatStateUsing(fn ($record) => [
										0 => 'Draft',
										1 => 'Published',
										2 => 'Hidden',
									][$record->status] ?? ''),
								Column::make('created_at')->heading('Created At'),
							])
							->withChunkSize(500)
							->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
							->withWriterType(Excel::CSV),
					]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInteractions::route('/'),
            'create' => Pages\CreateInteraction::route('/create'),
            'edit' => Pages\EditInteraction::route('/{record}/edit'),
        ];
    }
}
