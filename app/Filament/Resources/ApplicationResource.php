<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationResource\Pages;
use App\Filament\Resources\ApplicationResource\RelationManagers;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Application Name')
                            ->helperText('The name of the application requesting API access'),

                        Textarea::make('description')
                            ->placeholder('Application Description')
                            ->helperText('A brief description of the application and its purpose')
                            ->rows(3),

                        TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->placeholder('https://example.com/webhook')
                            ->helperText('Optional webhook URL for notifications'),

                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Toggle application access'),

                        TextInput::make('api_key')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('API Key is automatically generated')
                            ->visibleOn('edit'),

                        Forms\Components\KeyValue::make('settings')
                            ->label('Additional Settings')
                            ->helperText('Add any additional configuration key-value pairs')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Setting Value')
                            ->keyPlaceholder('Enter setting key')
                            ->valuePlaceholder('Enter setting value')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                // TextColumn::make('api_key')
                //     ->searchable()
                //     ->toggledHiddenByDefault()
                //     ->copyable()
                //     ->tooltip('Click to copy'),

                ToggleColumn::make('status')
                    ->label('Active')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
            ])
            ->actions([
                Tables\Actions\Action::make('generate_token')
                    ->label('Generate Token')
                    ->icon('heroicon-o-plus-circle')
                    ->action(function (Application $record) {
                        $token = $record->createToken(
                            'API Token - ' . now()->format('Y-m-d H:i:s')
                        );
                        return redirect()->back()->with('token', $token->plainTextToken);
                    })
                    ->requiresConfirmation(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
