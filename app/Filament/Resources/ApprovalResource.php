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
use App\Services\PointComponentService;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ApprovalResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ApprovalResource\RelationManagers;

class ApprovalResource extends Resource
{
    protected static ?string $model = Approval::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Approvals';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $roles = $user->roles()->pluck('id')->toArray();

        $query->whereHas('approvalSetting', function ($query) use ($roles) {
            $query->whereIn('role_id', $roles);
        })->where('approved', false);

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
                TextColumn::make('approvable_type')
                    ->label('Approvable Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('approvable.name')
                    ->label('Approvable Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                    ->label('Data')
                    ->limit(30),
                BadgeColumn::make('approved')
                    ->label('Status')
                    ->enum([
                        '1' => 'Approved',
                        '0' => 'Pending',
                    ])
            ])
            ->filters([
                //
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
                                }
                            } else {
                                Notification::make()
                                    ->title('Unable to approve. Awaiting approval from lower sequence user.')
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
                BulkAction::make('reject')
                    ->label('Reject')
                    ->action(function (Approval $record): void {
                        $record->update(['approved' => false]);
                    }),
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
            'index' => Pages\ListApprovals::route('/'),
            'create' => Pages\CreateApproval::route('/create'),
            'edit' => Pages\EditApproval::route('/{record}/edit'),
        ];
    }
}
