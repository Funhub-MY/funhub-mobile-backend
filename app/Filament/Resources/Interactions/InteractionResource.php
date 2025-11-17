<?php

namespace App\Filament\Resources\Interactions;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Interactions\Pages\ListInteractions;
use App\Filament\Resources\Interactions\Pages\CreateInteraction;
use App\Filament\Resources\Interactions\Pages\EditInteraction;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use App\Models\Interaction;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options(Interaction::STATUS)
                    ->default(0)
                    ->required(),
                Select::make('type')
                    ->options([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ])
                    ->default(0)
                    ->required(),
                Select::make('user_id')
                    ->label('Belongs To User')
                    ->preload()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(25))
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->default(fn () => User::where('id', auth()->user()->id)?->first()->id)
                    ->relationship('user','name'),
                MorphToSelect::make('interactable')
                    ->types([
                        // TODO:: at the moment there is only 1 type.
                        Type::make(Article::class)->titleColumnName('title'),
                        Type::make(MerchantOffer::class)->titleColumnName('name'),
                    ])
                    ->label('Type'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable()
                    ->label('By User'),
                TextColumn::make('type')
                    ->enum([
                        1 => 'Like',
                        2 => 'Dislike',
                        3 => 'Share',
                        4 => 'Bookmark',
                    ]),
                TextColumn::make('meta'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Draft',
                        1 => 'Published',
                        2 => 'Hidden',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'primary',
                        1 => 'success',
                        2 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            'index' => ListInteractions::route('/'),
            'create' => CreateInteraction::route('/create'),
            'edit' => EditInteraction::route('/{record}/edit'),
        ];
    }
}
