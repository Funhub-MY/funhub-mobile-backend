<?php

namespace App\Filament\Resources\BlacklistSeederUsers;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\BlacklistSeederUsers\Pages\ListBlacklistSeederUsers;
use App\Filament\Resources\BlacklistSeederUsers\Pages\CreateBlacklistSeederUser;
use App\Filament\Resources\BlacklistSeederUsers\Pages\EditBlacklistSeederUser;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextColumn::make('user.email')
                    ->label('User'),
                TextColumn::make('created_at'),
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
            'index' => ListBlacklistSeederUsers::route('/'),
            'create' => CreateBlacklistSeederUser::route('/create'),
            'edit' => EditBlacklistSeederUser::route('/{record}/edit'),
        ];
    }    
}
