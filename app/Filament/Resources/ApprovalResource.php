<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Reward;
use App\Models\Approval;
use Filament\Resources\Form;
use Filament\Resources\Table;
use App\Services\PointService;
use App\Models\ApprovalSetting;
use App\Models\RewardComponent;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use App\Services\PointComponentService;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ApprovalResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ApprovalResource\RelationManagers;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ApprovalResource extends Resource
{
    protected static ?string $model = Approval::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Approvals';

    protected static function getNavigationBadge(): ?string
    {
        $pendingApprovals = static::getModel()::where('approver_id', auth()->user()->id)->where('approved', false)->count();
        return ($pendingApprovals > 0) ? $pendingApprovals : null;
    }


    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }


    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $roles = $user->roles()->pluck('id')->toArray();

        $query->whereHas('approvalSetting', function ($query) use ($roles) {
            $query->whereIn('role_id', $roles);
        });
        // ->where('approved', false);

        $query->orderBy('approved', 'asc')
        ->orderBy('created_at', 'desc');

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('approvable_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('approvable.created_at')
                    ->label('Requested At')
                    ->date('d/m/Y h:iA')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('approved')
                    ->label('Status')
                    ->enum([
                        '1' => 'Approved',
                        '0' => 'Pending',
                    ])
                    ->colors([
                        'success' => 1,
                        'warning' => 0,
                    ]),
                TextColumn::make('approvable_type')
                    ->label('Approvable Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('approvable.name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                ->label('Action')
                ->getStateUsing(function (Model $record) {
                        $data = json_decode($record->data, true);
                        $user = User::where('id', $data['user']['id'])->first();
                        $userIdentifiable = $user->email;
                        if (!$userIdentifiable) {
                            $userIdentifiable = '+'. $user->phone_country_code . $user->phone_no;
                        }

                        $user = $user ? $user->name . '('.$userIdentifiable.')' : '';

                        $html = '';
                        if ($record->approvable_type == Reward::class) {
                            $reward = Reward::find($record->approvable_id);
                            $html .= 'Credit <b>' . $data['quantity'] . '</b> '. $reward->name . ' to <b>' . $user . '</b>';
                        } elseif ($record->approvable_type == RewardComponent::class) {
                            $rewardComponent = RewardComponent::find($record->approvable_id);
                            $html .= 'Credit <b>' . $data['quantity'] . '</b> '. $rewardComponent->name . ' to <b>' . $user . '</b>';
                        } else {
                            $html .= $record->data; // raw
                        }

                        return $html;
                    })
                    ->html()

            ])
            ->filters([
                SelectFilter::make('approvable_type')
                    ->label('Approvable Type')
                    ->options([
                        'App\Models\Reward' => 'Reward',
                        'App\Models\RewardComponent' => 'Reward Component',
                    ]),

                SelectFilter::make('approved')
                    ->label('Status')
                    ->options([
                        '1' => 'Approved',
                        '0' => 'Pending',
                    ]),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('approve')
                    ->label('Approve')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $prevSequenceApproved = ApprovalSetting::where('approvable_type', $record->approvable_type)
                                ->where('sequence', '=', $record->approvalSetting->sequence - 1)
                                ->whereHas('approvals', function ($query) {
                                    $query->where('approved', true);
                                })->exists();

                            // check if only have one sequence in all approval settings
                            $onlyOneSequence = ApprovalSetting::where('approvable_type', $record->approvable_type)
                                ->count() == 1;

                            // if only one sequence then prevsequence approved auto pass
                            if ($onlyOneSequence) {
                                $prevSequenceApproved = true;
                            }

                            if ($prevSequenceApproved) {
                                $user = auth()->user();
                                $record->update([
                                    'approved' => true,
                                    'approver_id' => $user->id,
                                ]);

                                $user = auth()->user();
                                $userRoles = $user->roles()->pluck('id')->toArray();
                                $maxSequenceRoleId = ApprovalSetting::where('approvable_type', $record->approvable_type)
                                    ->orderBy('sequence', 'desc')
                                    ->value('role_id');

                                if (in_array($maxSequenceRoleId, $userRoles)) {
                                    $data = json_decode($record->data, true);
                                    $user = User::where('id', $data['user']['id'])->first();

                                    switch ($data['action']) {
                                        case 'reward-user':
                                            if ($record->approvable_type === 'App\Models\Reward') {
                                                $reward = Reward::find($record->approvable_id);
                                                if ($reward) {
                                                    $pointService = new PointService();
                                                    $pointService->credit($reward, $user, $data['quantity'], 'Manual Reward', 'Rewarding points');
                                                }
                                            } elseif ($record->approvable_type === 'App\Models\RewardComponent') {
                                                $rewardComponent = RewardComponent::find($record->approvable_id);
                                                if ($rewardComponent) {
                                                    $pointComponentService = new PointComponentService();
                                                    $pointComponentService->credit(auth()->user(), $rewardComponent, $user, $data['quantity'], 'Manual Reward', 'Rewarding components');
                                                }
                                            }
                                            break;
                                    }

                                    Notification::make()
                                        ->success()
                                        ->title('Approved ID: '.$record->id)
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('Unable to approve. Awaiting approval from lower sequence user.')
                                    ->warning()
                                    ->send();
                            }
                        }
                    })
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation(),
                BulkAction::make('reject')
                    ->label('Reject')
                    ->action(function (Approval $record): void {
                        // if record already approved warning and ignore
                        if ($record->approved) {
                            Notification::make()
                                ->title('Unable to reject. Record already approved.')
                                ->warning()
                                ->send();
                            return;
                        }
                        $record->update(['approved' => false]);
                    })
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation(),
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
            'index' => Pages\ListApprovals::route('/'),
            'create' => Pages\CreateApproval::route('/create'),
            'edit' => Pages\EditApproval::route('/{record}/edit'),
        ];
    }
}
