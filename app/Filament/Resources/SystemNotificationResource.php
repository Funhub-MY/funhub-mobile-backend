<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SystemNotificationResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SystemNotificationResource\RelationManagers;

class SystemNotificationResource extends Resource
{
    protected static ?string $model = SystemNotification::class;

    protected static ?string $navigationGroup = 'Notification';

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->columnSpan('full')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        $wordCount = str_word_count($value);

                                        if ($wordCount >50 ) {
                                            $fail('The :attribute cannot exceed 50 words');
                                        }
                                    };
                                }
                            ])
                            ->required(),

                        Textarea::make('content')
                            ->label('Content')
                            ->columnSpan('full')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        $wordCount = str_word_count($value);

                                        if ($wordCount >50 ) {
                                            $fail('The :attribute cannot exceed 50 words');
                                        }
                                    };
                                }
                            ])
                            ->required(),

                        DateTimePicker::make('scheduled_at')
                            ->minDate(now())
                            ->label('Schedule Blast Time')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'web' => 'Web',
                                'text' => 'Text',
                            ])
                            ->reactive()
                            ->required(),

                        TextInput::make('web_link')
                            ->label('Web Link')
                            ->hidden(fn (Closure $get) => $get('type') !== 'web'),
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Select::make('user')
                            // ->label('Users')
                            ->preload()
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('username', 'like', "%{$search}%")->limit(25)->pluck('username', 'id'))
                            ->placeholder('Enter username or select by user status')
                            ->hidden(fn (Closure $get) => $get('all_active_users') === true)
                            ->dehydrateStateUsing(function ($state) {
                                    $stateData = [];
                                    foreach ($state as $s) {
                                        $stateData[] = intval($s);
                                    }

                                    return json_encode($stateData);
                                }),

                        Toggle::make('all_active_users')
                            ->label('Toggle on to send notification to all active users')
                            ->reactive(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('content')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('scheduled_at')
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
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemNotifications::route('/'),
            'create' => Pages\CreateSystemNotification::route('/create'),
            'edit' => Pages\EditSystemNotification::route('/{record}/edit'),
        ];
    }
}
