<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Reports;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ReportResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ReportResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ReportResource extends Resource
{
    protected static ?string $model = Reports::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Reason & Resolutions')
                            ->schema([
                                Forms\Components\TextInput::make('reason')
                                    ->required(),
                                Forms\Components\TextInput::make('resolution'),
                                Forms\Components\Select::make('resolved')
                                    ->options([
                                        1 => 'Resolved',
                                        0 => 'Not Resolved'
                                    ])
                                    ->default(0)
                                    ->label('Resolved Status')
                                    ->reactive()
                                    ->afterStateUpdated(function (\Closure $set, \Closure $get, $state, string $context) {
                                        if ($state == 1) {
                                            $set('resolved_at', Carbon::now()->toDateString());
                                            $set('resolved_by', auth()->user()->id);
                                        } else {
                                            $set('resolved_at', null);
                                            $set('resolved_by', null);
                                        }
                                    }),
                            ])
                        ->inlineLabel(),
                        Forms\Components\Section::make('Violations')
                            ->schema([
                                Forms\Components\Select::make('violation_level')
                                    ->options([
                                        1 => '1',
                                        2 => '2',
                                        3 => '3',
                                    ])
                                    ->label('Violation Level')
                                    ->default(1)
                                    ->required()
                                    ->reactive(),
                                Forms\Components\Select::make('violation_type')
                                    ->options(function (\Closure $get) {
                                        $level = $get('violation_level');
                                        if ($level == 1) {
                                            return [
                                                'Plagiarism' => 'Plagiarism',
                                                'Violence and Criminal Behavior, Inappropriate Speech or Content' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
                                                'Intellectual Property' => 'Intellectual Property'
                                            ];
                                        } else if ($level == 2) {
                                            return [
                                                'Repeat Violations after Receiving Warnings' => 'Repeat Violations after Receiving Warnings'
                                            ];
                                        } else if ($level == 3) {
                                            return [
                                                'Repeated Violations of FunHub Community Guidelines' => 'Repeated Violations of FunHub Community Guidelines'
                                            ];
                                        } else {
                                            return [];
                                        }
                                    })->required(),
                            ])
                            ->inlineLabel(),
                        // set reactive to resolved
                        // if reactive to 1, set resolved_at, resolved_by to the perspective data.
                        Forms\Components\Hidden::make('resolved_at'),
                        Forms\Components\Hidden::make('resolved_by'),
                        Forms\Components\Hidden::make('user_id')->default(auth()->user()->id)
                    ]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Targeted')
                            ->schema([
                                Forms\Components\MorphToSelect::make('reportable')
                                    ->types([
                                        Forms\Components\MorphToSelect\Type::make(Article::class)->titleColumnName('title'),
                                        Forms\Components\MorphToSelect\Type::make(User::class)->titleColumnName('name'),
                                        Forms\Components\MorphToSelect\Type::make(Comment::class)->titleColumnName('body'),
                                    ])
                                    ->label('Type'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('reason')
                    ->wrap()
                    ->label('Reported Reason'),
                Tables\Columns\BadgeColumn::make('resolved')
                    ->enum([
                        0 => 'Not Resolved',
                        1 => 'Resolved',
                    ])
                    ->colors([
                        'warning' => static fn ($state): bool => $state === 0,
                        'success' => static fn ($state): bool => $state === 1,
                    ]),
                Tables\Columns\TextColumn::make('reportable_type')
                    ->getStateUsing(function(Model $record){
                        // switch case
                        if ($record->reportable_type == User::class) {
                            return 'User';
                        } else if ($record->reportable_type == Comment::class) {
                            return 'Comment';
                        } else if ($record->reportable_type == Article::class) {
                            return 'Article';
                        } else {
                            return '-';
                        }
                    })
                    ->label('Reported on'),
                Tables\Columns\TextColumn::make('resolved_at'),
                Tables\Columns\TextColumn::make('resolvedBy.name'),
                Tables\Columns\TextColumn::make('resolution')->wrap(),
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
            'index' => Pages\ListReports::route('/'),
            'create' => Pages\CreateReport::route('/create'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
        ];
    }
}
