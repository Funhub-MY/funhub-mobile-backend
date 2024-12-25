<?php

namespace App\Filament\Resources;

use App\Models\Article;
use App\Models\Merchant;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Models\SupportRequest;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\SupportRequestResource\Pages;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SupportRequestResource\RelationManagers;
use App\Filament\Resources\SupportRequestResource\RelationManagers\MessagesRelationManager;
use Filament\Tables\Filters\SelectFilter;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;

    protected static ?string $navigationGroup = 'Help Center';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

	protected static function getNavigationBadge(): ?string
	{
		return (string) SupportRequest::where('status', '!=', SupportRequest::STATUS_CLOSED)->count();
	}

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Section::make('Support Request')
                        ->schema([
                            TextInput::make('title')
                                ->required(),

                            Select::make('category_id')
                                ->relationship('category', 'name')
                                ->required(),

							Forms\Components\MorphToSelect::make('supportable')
								->types([
									// TODO:: at the moment there is only 1 type.
									Forms\Components\MorphToSelect\Type::make(Article::class)
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

                BadgeColumn::make('status')
                    ->enum([
                        0 => 'Pending',
                        1 => 'In Progress',
                        2 => 'Pending Info',
                        3 => 'Closed',
                        4 => 'Reopened',
                        5 => 'Invalid'
                    ])
                    ->colors([
                        'secondary' => 0,
                        'info' => 1,
                        'warning' => 2,
                        'success' => 3,
                        'danger' => 4,
                        'secondary' => 5,
                    ]),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),

                TextColumn::make('requestor.name')
                    ->label('Requestor')
                    ->searchable(),

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
                    ->relationship('assignee', 'name', function (Builder $query) {
                        $query->whereNotNull('name')->withTrashed();
                    }),

                SelectFilter::make('requestor_id')
                    ->label('Requestor')
                    ->relationship('requestor', 'name', function (Builder $query) {
                        $query->whereNotNull('name')->withTrashed();
                    }),
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
            MessagesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportRequests::route('/'),
            'create' => Pages\CreateSupportRequest::route('/create'),
            'edit' => Pages\EditSupportRequest::route('/{record}/edit'),
        ];
    }
}
