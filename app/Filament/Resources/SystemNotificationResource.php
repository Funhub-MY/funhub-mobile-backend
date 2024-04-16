<?php

namespace App\Filament\Resources;

use Closure;
use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use App\Models\MerchantOffer;
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
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SystemNotificationResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SystemNotificationResource\RelationManagers;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\BadgeColumn;

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
                            ->label('Schedule Blast Time')
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        if (now()->greaterThan(Carbon::parse($value))) {
                                            $fail('The :attribute cannot be in the past');
                                        }
                                    };
                                }
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Radio::make('redirect_type')
                            ->label('Redirect Type')
                            ->options(SystemNotification::REDIRECT_TYPE)
                            ->default(SystemNotification::REDIRECT_STATIC)
                            ->reactive()
                            ->required()
                            ->columnSpanFull(),

                        MorphToSelect::make('content')
                            ->label('Dynamic Redirect')
                            ->types([
                                MorphToSelect\Type::make(Article::class)
                                    ->label('Article')
                                    ->titleColumnName('title'),
                                MorphToSelect\Type::make(MerchantOffer::class)
                                    ->label('Deal')
                                    ->titleColumnName('name'),
                                MorphToSelect\Type::make(User::class)
                                    ->label('User')
                                    ->titleColumnName('username'),
                            ])
                            ->hidden(function ($get) {
                                if ($get('redirect_type') == SystemNotification::REDIRECT_DYNAMIC) {
                                    return false;
                                }

                                return true;
                            })
                            ->reactive()
                            ->required()
                            ->columnSpanFull()
                            ->searchable(),

                        Fieldset::make()
                            ->schema([
                                Select::make('static_content_type')
                                    ->label('Content Type')
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
                            ->hidden(function ($get) {
                                if ($get('redirect_type') == SystemNotification::REDIRECT_STATIC) {
                                    return false;
                                }

                                return true;
                            })
                            ->label('Static Redirect')
                            ->columns(1)
                    ])
                    ->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Select::make('user')
                            ->preload()
                            ->multiple()
                            ->searchable()
                            ->options(User::pluck('username', 'id')->toArray())
                            // ->getSearchResultsUsing(fn (string $search) => User::where('username', 'like', "%{$search}%")->limit(25)->pluck('username', 'id'))
                            ->placeholder('Enter username or select by user status')
                            ->hidden(fn (Closure $get) => $get('all_active_users') === true)
                            ->dehydrateStateUsing(function ($state) {
                                    $stateData = [];
                                    foreach ($state as $s) {
                                        $stateData[] = intval($s);
                                    }

                                    return json_encode($stateData);
                                })
                            ->formatStateUsing(function ($context, $state) {
                                if ($context == 'edit') {
                                    $stateData = json_decode($state, true);
                                    return $stateData;
                                }
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

                TextColumn::make('title')
                    ->sortable()
                    ->searchable()
                    ->words(5),

                TextColumn::make('content')
                    ->words(8),

                TextColumn::make('redirect_type')
                    ->enum(SystemNotification::REDIRECT_TYPE)
                    ->sortable(),

                TextColumn::make('content_type')
                    ->label('Dynamic Content Type')
                    ->sortable(),

                TextColumn::make('content_id')
                    ->label('Dynamic Content ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('static_content_type')
                    ->label('Static Content type')
                    ->sortable(),

                TextColumn::make('web_link')
                    ->label('Web Link'),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled At')
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->sortable(),

                // TextColumn::make('user')
                //     ->label('Notified User')
                //     ->formatStateUsing(function ($state) {
                //         if ($state) {
                //             $usernames = [];
                //             $stateData = json_decode($state, true);

                //             $usernames = User::whereIn('id', $stateData)->pluck('username')->toArray();
                //             return implode(', ', $usernames);
                //         }
                //     })
                //     ->wrap(),

                BadgeColumn::make('all_active_users')
                    ->label('All Active Users')
                    ->enum([
                        0 => "False",
                        1 => "True",
                    ])
                    ->colors([
                        'warning' => 0,
                        'success' => 1,
                    ]),

                TextColumn::make('created_at')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
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
