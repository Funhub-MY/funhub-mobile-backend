<?php

namespace App\Filament\Resources\BadgeResource\RelationManagers;

use App\Models\User;
use App\Models\UserBadge;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserBadgesRelationManager extends RelationManager
{
    protected static string $relationship = 'userBadges';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Users Awarded';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                DateTimePicker::make('earned_at')
                    ->label('Earned At')
                    ->default(now())
                    ->required(),

                Toggle::make('is_active')
                    ->label('Showcase Badge')
                    ->helperText('Display this badge on user\'s profile'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('reservation.id')
                    ->label('Reservation #')
                    ->url(fn ($record) => $record->reservation_id 
                        ? route('filament.resources.reservations.view', $record->reservation_id) 
                        : null)
                    ->toggleable(),

                TextColumn::make('earned_at')
                    ->label('Earned At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Showcase')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('earned_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('is_showcase')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->label('Showcase Only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Award Badge Manually'),
            ])
            ->actions([
                Tables\Actions\Action::make('setShowcase')
                    ->label('Set as Showcase')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(function (UserBadge $record) {
                        $record->setAsShowcase();
                    })
                    ->visible(fn (UserBadge $record) => !$record->is_active)
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Revoke Selected'),
            ]);
    }
}

