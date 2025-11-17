<?php

namespace App\Filament\Resources\Reports;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Reports;
use Filament\Tables\Table;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Reason & Resolutions')
                            ->schema([
                                TextInput::make('reason')
                                    ->required(),
                                TextInput::make('resolution'),
                                Select::make('resolved')
                                    ->options([
                                        1 => 'Resolved',
                                        0 => 'Not Resolved'
                                    ])
                                    ->default(0)
                                    ->label('Resolved Status')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state, string $context) {
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
                        Section::make('Violations')
                            ->schema([
                                Select::make('violation_level')
                                    ->options([
                                        1 => '1',
                                        2 => '2',
                                        3 => '3',
                                    ])
                                    ->label('Violation Level')
                                    ->default(1)
                                    ->required()
                                    ->reactive(),
                                Select::make('violation_type')
                                    ->options(function (Get $get) {
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
                        Hidden::make('resolved_at'),
                        Hidden::make('resolved_by'),
                        Hidden::make('user_id')->default(auth()->user()->id)
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Targeted')
                            ->schema([
                                MorphToSelect::make('reportable')
                                    ->types([
                                        Type::make(Article::class)->titleColumnName('title'),
                                        Type::make(User::class)->titleColumnName('name'),
                                        Type::make(Comment::class)->titleColumnName('body'),
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
                TextColumn::make('user.name')
                    ->label('By User'),
                TextColumn::make('reason')
                    ->wrap()
                    ->label('Reported Reason'),
                TextColumn::make('resolved')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Not Resolved',
                        1 => 'Resolved',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'warning',
                        1 => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('reportable_type')
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
                TextColumn::make('resolved_at'),
                TextColumn::make('resolvedBy.name'),
                TextColumn::make('resolution')->wrap(),
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
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }
}
