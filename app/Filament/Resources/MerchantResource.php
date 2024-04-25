<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SebastianBergmann\Type\NullType;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\CreateRecord;
use App\Notifications\MerchantOnboardEmail;
use App\Filament\Resources\MerchantResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Filament\Resources\MerchantResource\RelationManagers;
use App\Filament\Resources\MerchantResource\RelationManagers\StoresRelationManager;
use App\Models\Store;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Merchant';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationBadge(): ?string
    {
        $pendingApprovals = Merchant::where('status', Merchant::STATUS_PENDING)->count();

        return ($pendingApprovals > 0) ? (string) $pendingApprovals : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $query
        ->orderBy('status', 'asc')
        ->orderBy('created_at', 'desc');

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Merchant Name')
                            ->autofocus()
                            ->required()
                            ->rules('required', 'max:255'),

                        TextInput::make('redeem_code')
                            ->label('Cashier Redeem Code (6 Digit)')
                            ->disabled(fn ($livewire) => $livewire instanceof CreateRecord)
                            ->rules('digits:6')
                            ->disabled()
                            ->numeric()
                            ->unique(Merchant::class, 'redeem_code', ignoreRecord: true)
                            ->helperText('Auto-generated, used when cashier validates merchant offers, will be provided to user during offer redemption in store.123'),

                        TextInput::make('email')
                            ->label('Email (used for Login)')
                            ->email(true)
                            ->helperText('System auto send an email with their Login Email, Password to this address when created.')
                            ->required()
                            ->rules(['email', 'required', function ($context, ?Model $record) {
                                return function (string $attribute, $value, Closure $fail) use ($context, $record) {
                                    if ($context === 'create' || !$record) {
                                        $is_user_exists = User::where('email', $value) // check if email already existed in the User table,
                                            ->exists();
                                    } elseif ($context === 'edit' && $record instanceof Model)  {
                                        $is_user_exists = User::where('email', $value) // check if email already existed in the User table,
                                            ->where('id', '!=', $record->user_id) // excluding current user record in the table
                                            ->exists();
                                    }

                                    // Check the result and fail if the email already exists
                                    if ($is_user_exists) {
                                        $fail('The :attribute is exists');
                                    }
                                };
                            }])
                    ]),
                Forms\Components\Section::make('Business Information')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->label('Name')
                            ->rules('required', 'max:255'),
                        Forms\Components\TextInput::make('business_phone_no')
                            ->required()
                            ->label('Phone Number')
                            ->rules('required'),
                        Forms\Components\Textarea::make('address')
                            ->required(),
                        Forms\Components\TextInput::make('address_postcode')
                            ->required(),
                    ]),
                Forms\Components\Section::make('Person In Charge Information')
                    ->schema([
                        Forms\Components\TextInput::make('pic_name')
                            ->label('Name')
                            ->required(),
                        Forms\Components\TextInput::make('pic_phone_no')
                            ->label('Phone Number')
                            ->required(),
                        Forms\Components\TextInput::make('pic_email')
                            ->label('Email')
                            ->helperText('For record purposes only, not used for login.')
                            ->required()
                            ->rules('required', 'email')
                    ]),

                Forms\Components\Section::make('Photos')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('company_photos')
                        ->label('Company Photos')
                        ->multiple()
                        ->maxFiles(7)
                        ->collection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)
                        ->required()
                        ->columnSpan('full')
                        ->disk(function () {
                            if (config('filesystems.default') === 's3') {
                                return 's3_public';
                            }
                        })
                        ->acceptedFileTypes(['image/*'])
                        ->rules('image'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('status')
                    ->label('Status')
                    ->enum([
                        0 => 'Pending',
                        1 => 'Approved',
                        2 => 'Rejected',
                    ])
                    ->colors([
                        'warning' => 0,
                        'success' => 1,
                        'danger' => 2,
                    ]),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By User'),
                Tables\Columns\TextColumn::make('business_name'),
                Tables\Columns\TextColumn::make('redeem_code'),
                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('address_postcode'),
                Tables\Columns\TextColumn::make('pic_name'),
                Tables\Columns\TextColumn::make('pic_phone_no'),
                Tables\Columns\TextColumn::make('pic_email'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('sendEmail')
                    ->label('Send Merchant Onboard Email')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if (empty($record->default_password)) {
                                $record->default_password = Str::random(8);
                                $record->save();

                                $user = $record->user;
                                $user->password = bcrypt($record->default_password);
                                $user->save();
                            }
                            $record->user->notify(new MerchantOnboardEmail($record->name, $record->user->email, $record->default_password, $record->redeem_code));

                            Notification::make()
                                ->title('Sent to ' . $record->user->email)
                                ->success()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('Approve Merchant')
                    ->label('Approve Merchant')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->update(['status' => Merchant::STATUS_APPROVED]);

                            if (empty($record->default_password)) {
                                $record->default_password = Str::random(8);
                                $record->save();

                                $user = $record->user;
                                $user->password = bcrypt($record->default_password);
                                $user->save();
                            }
                            $record->user->notify(new MerchantOnboardEmail($record->name, $record->user->email, $record->default_password, $record->redeem_code));

                            Notification::make()
                                ->success()
                                ->title('Approved Merchant ID: '.$record->id)
                                ->send();
                        }
                    })
                    ->icon('heroicon-o-check-circle')
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation(),
                BulkAction::make('Reject Merchant')
                    ->label('Reject Merchant')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            // if record already approved warning and ignore
                            if ($record['status'] === Merchant::STATUS_APPROVED) {
                                Notification::make()
                                    ->title('Unable to reject Merchant ID: '.$record->id. ' Record already approved.')
                                    ->warning()
                                    ->send();
                            } else {
                                $record->update(['status' => Merchant::STATUS_REJECTED]);

                                Notification::make()
                                    ->success()
                                    ->title('Rejected Merchant ID: '.$record->id)
                                    ->send();
                            }
                        }
                    })
                    ->icon('heroicon-o-x-circle')
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StoresRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
            return [
                'index' => Pages\ListMerchants::route('/'),
                'create' => Pages\CreateMerchant::route('/create'),
                'edit' => Pages\EditMerchant::route('/{record}/edit'),
            ];
    }
}
