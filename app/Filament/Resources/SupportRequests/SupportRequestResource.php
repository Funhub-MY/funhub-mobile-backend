<?php

namespace App\Filament\Resources\SupportRequests;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Models\Article;
use App\Models\Merchant;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\SupportRequest;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SupportRequestResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SupportRequestResource\RelationManagers;
use App\Filament\Resources\SupportRequests\RelationManagers\MessagesRelationManager;
use Filament\Tables\Filters\SelectFilter;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 1;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

	public static function getNavigationBadge(): ?string
	{
		return (string) SupportRequest::where('status', '!=', SupportRequest::STATUS_CLOSED)->count();
	}

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Support Request')
                        ->schema([
                            TextInput::make('title')
                                ->required(),

                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->required(),

							MorphToSelect::make('supportable')
								->types([
									// TODO:: at the moment there is only 1 type.
									Type::make(Article::class)
										->titleColumnName('title')
										->getOptionLabelUsing(function ($value): string {
											$article = Article::find($value);
											return "({$article->id}) {$article->title}";
										})
										->getSearchResultsUsing(function (string $search): array {
											return Article::query()
												->where('title', 'like', "%{$search}%")
												->limit(50)
												->get()
												->mapWithKeys(fn (Article $article) => [
													$article->id => "({$article->id}) {$article->title}"
												])
												->toArray();
										})
								])
								->searchable()
								->label('Type'),

                            TextInput::make('internal_remarks'),
                        ])
                ])->columnSpan(['lg' => 2]),
                Group::make([
                    Section::make('Other Info')
                        ->schema([
                            Select::make('status')
                                ->options(SupportRequest::STATUS)
                                ->required(),

                            Select::make('requestor_id')
                                ->relationship('requestor', 'name')
								->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} (ID: {$record->id})")
								->searchable(),

                            Select::make('assignee_id')
                                ->label('Assignee (Admin/Staff)')
                                ->preload()
                                ->getSearchResultsUsing(function (string $search): array {
                                    return User::query()
                                        ->where(function (Builder $builder) use ($search) {
                                            $searchString = "%$search%";
                                            $builder->where('name', 'like', $searchString);
                                        })
                                        ->whereHas('roles', function ($query) {
                                            $query->whereIn('name', ['super_admin', 'moderator', 'staff']);
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function (User $user) {
                                            return [$user->id => $user->name.' ('.$user->email.')'];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function ($value) {
                                    $user = User::find($value);
                                    return $user->name.' ('.$user->email.')';
                                })
                                ->preload()
                                ->searchable(),
                        ])
                ])->columnSpan(['lg' => 2])
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->date('d/m/Y h:iA')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        0 => 'Pending',
                        1 => 'In Progress',
                        2 => 'Pending Info',
                        3 => 'Closed',
                        4 => 'Reopened',
                        5 => 'Invalid',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        0 => 'secondary',
                        1 => 'info',
                        2 => 'warning',
                        3 => 'success',
                        4 => 'danger',
                        5 => 'secondary',
                        default => 'gray',
                    }),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),

                TextColumn::make('requestor.name')
                    ->label('Requestor')
                    ->searchable()
					->formatStateUsing(function ($state, $record) {
						if ($record->requestor) {
							return "{$record->requestor->name} (ID: {$record->requestor->id})";
						}
						return null;
					}),

                TextColumn::make('assignee.name')
                    ->label('Assignee')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        0 => 'Pending',
                        1 => 'In Progress',
                        2 => 'Pending Info',
                        3 => 'Closed',
                        4 => 'Reoepend',
                        5 => 'Invalid',
                    ])
                    ->label('Status'),

                SelectFilter::make('assignee_id')
                    ->label('Assignee')
					->searchable()
                    ->relationship('assignee', 'name', function (Builder $query) {
                        $query->whereNotNull('name')->withTrashed();
                    }),

                SelectFilter::make('requestor_id')
                    ->label('Requestor')
					->searchable()
					->relationship('requestor', 'name', function (Builder $query) {
                        $query->whereNotNull('name')->withTrashed();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportRequests::route('/'),
            'create' => CreateSupportRequest::route('/create'),
            'edit' => EditSupportRequest::route('/{record}/edit'),
        ];
    }
}
