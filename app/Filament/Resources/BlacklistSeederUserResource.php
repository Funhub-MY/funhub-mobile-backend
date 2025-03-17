<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\BlacklistSeederUser;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\BlacklistSeederUserResource\Pages;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\BlacklistSeederUserResource\RelationManagers;

class BlacklistSeederUserResource extends Resource
{
    protected static ?string $model = BlacklistSeederUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(User::all()->pluck('email', 'id')) //use email instead of name because email is unique
                    ->searchable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User'),
                Tables\Columns\TextColumn::make('created_at'),
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
								Column::make('id')->heading('Id'),
								Column::make('user_id')->heading('User Id'),
								Column::make('user.name')->heading('User Name'),
							])
							->withChunkSize(500)
							->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
							->withWriterType(Excel::CSV),
					])
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
            'index' => Pages\ListBlacklistSeederUsers::route('/'),
            'create' => Pages\CreateBlacklistSeederUser::route('/create'),
            'edit' => Pages\EditBlacklistSeederUser::route('/{record}/edit'),
        ];
    }    
}
