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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Google\Service\StreetViewPublish\Place;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Nette\Utils\Html;

use App\Services\SyncMerchantPortal;

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
                Group::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Section::make('Basic Information')
                            ->columnSpan(1)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Account Status')
                                    ->options([
                                        Merchant::STATUS_PENDING => 'Pending',
                                        Merchant::STATUS_APPROVED => 'Approved',
                                        Merchant::STATUS_REJECTED => 'Rejected',
                                    ])
                                    ->required(),
                                    
                                Forms\Components\Select::make('user_id')
                                    ->label('Linked User Account')
                                    ->relationship('user', 'name')
                                    ->searchable(),
                                    // ->required(),
                                                                    // if edit context and record has auto linked show placeholder
                                Placeholder::make('business_phone_no')
                                    ->disableLabel(true)
                                    ->content(fn () => new HtmlString('<span style="font-weight:bold; color: #ff0000;font-size: 14px;margin-top: -5px">Auto linked with User as Registered Phone No. same as a Registered User </span>'))
                                    ->visible(fn (Closure $get) => $get('has_auto_linked_user') === true),

                                Forms\Components\TextInput::make('name')
                                    ->label('Merchant Name')
                                    ->autofocus()
                                    ->required()
                                    ->rules('required', 'max:255'),

                                Forms\Components\TextInput::make('brand_name')
                                    ->label('Brand Name')
                                    ->required()
                                    ->rules('max:255'),

                                TextInput::make('redeem_code')
                                    ->label('Cashier Redeem Code (6 Digit)')
                                    ->disabled(fn ($livewire) => $livewire instanceof CreateRecord)
                                    ->rules('digits:6')
                                    ->disabled()
                                    ->numeric()
                                    ->unique(Merchant::class, 'redeem_code', ignoreRecord: true)
                                    ->helperText('Auto-generated, used when cashier validates merchant offers, will be provided to user during offer redemption in store.123'),

                                // TextInput::make('email')
                                //     ->label('Email (used for Login)')
                                //     ->email(true)
                                //     ->helperText('System auto send an email with their Login Email, Password to this address when created.')
                                //     ->required()
                                //     ->rules(['email', 'required', function ($context, ?Model $record) {
                                //         return function (string $attribute, $value, Closure $fail) use ($context, $record) {
                                //             if ($context === 'create' || !$record) {
                                //                 $is_user_exists = User::where('email', $value) // check if email already existed in the User table,
                                //                     ->exists();
                                //             } elseif ($context === 'edit' && $record instanceof Model)  {
                                //                 $is_user_exists = User::where('email', $value) // check if email already existed in the User table,
                                //                     ->where('id', '!=', $record->user_id) // excluding current user record in the table
                                //                     ->exists();
                                //             }

                                //             // Check the result and fail if the email already exists
                                //             if ($is_user_exists) {
                                //                 $fail('The :attribute is exists');
                                //             }
                                //         };
                                //     }]),

                                TextInput::make('email')
                                    ->label('Email (used for Login)')
                                    ->email(true)
                                    ->required()
                                    ->helperText('System auto send an email with their Login Email, Password to this address when created.'),

                                // categories
                                Select::make('categories')
                                    ->label('Merchant Categories')
                                    ->relationship('categories', 'name')
                                    ->required()
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ]),
                        Forms\Components\Section::make('Business Information')
                            ->columnSpan(1)
                            ->schema([
                                Forms\Components\TextInput::make('business_name')
                                    ->required()
                                    ->label('Name')
                                    ->rules('required', 'max:255'),
                                TextInput::make('company_reg_no') //merchant's table new column 'company_reg_no'
                                    ->label('Registration Number')
                                    ->required()
                                    ->placeholder('Enter Registration Number'),
                                Forms\Components\TextInput::make('business_phone_no')
                                    ->required()
                                    ->label('Phone Number')
                                    ->rules('required'),
                                Forms\Components\Textarea::make('address')
                                    ->required(),
                                Forms\Components\TextInput::make('address_postcode')
                                    ->required(),
                            ]),
                ]),
                Group::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Forms\Components\Section::make('Person In Charge Information')
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('pic_name') //merchant's table 'pic_name'
                                    ->label('PIC Name')
                                    ->placeholder('Enter PIC Name'),
                                TextInput::make('pic_designation') //merchant's table new column 'pic_designation'
                                    ->label('Designation')
                                    ->placeholder('Enter Designation'),
                                TextInput::make('pic_ic_no') //merchant's table new column 'pic_ic_no'
                                    ->label('IC Number')
                                    ->placeholder('Enter IC Number'),
                                TextInput::make('pic_phone_no') //merchant's table column 'pic_phone_no'
                                    ->label('Contact Number')
                                    ->placeholder('Enter Contact Number'),
                                TextInput::make('pic_email') //merchant's table column 'pic_email'
                                    ->label('PIC Email')
                                    ->placeholder('Enter Email'),
                            ]),

                        Forms\Components\Section::make('Authorised Personnel Information')
                            ->columnSpan(1)
                            ->schema([
                                Placeholder::make('authorised_personnel_information')
                                    ->label('Authorised Personnel are people who has authority to sign contract on behalf of business'),
                                TextInput::make('authorised_personnel_designation')
                                    ->label('Authorised Personnel Designation'),
                                TextInput::make('authorised_personnel_name')
                                    ->label('Authorised Personnel Name'),
                                TextInput::make('authorised_personnel_ic_no')
                                    ->label('Authorised Personnel IC Number'),
                            ]),
						Forms\Components\Select::make('koc_user_id')
							->label('KOC User')
							->relationship('kocUser', 'name')
							->searchable(),
                        ]),
                Forms\Components\Section::make('Photos')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('company_logo')
                            ->label('Company Logo')
							->required()
                            ->maxFiles(1)
                            ->collection(Merchant::MEDIA_COLLECTION_NAME)
                            // ->required()
                            ->enableDownload(true)
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->rules('image'),

                        SpatieMediaLibraryFileUpload::make('company_photos')
                            ->label('Company Photos')
                            ->multiple()
                            ->maxFiles(7)
                            ->enableDownload(true)
                            ->collection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)
                            // ->required()
                            ->columnSpan('full')
                            ->disk(function () {
                                if (config('filesystems.default') === 's3') {
                                    return 's3_public';
                                }
                            })
                            ->acceptedFileTypes(['image/*'])
                            ->rules('image'),

                        // Repeater::make('menus')
                        //     ->label('Menus')
                        //     ->createItemButtonLabel('Add Menu')
                        //     ->schema([
                        //         TextInput::make('name')
                        //             ->label('Menu Name')
                        //             ->reactive()
                        //             ->required(),
                        //         FileUpload::make('file')
                        //             ->label('Menu File (PDF ONLY)')
                        //             ->disk(function () {
                        //                 if (config('filesystems.default') === 's3') {
                        //                     return 's3_public';
                        //                 }
                        //             })
                        //             ->required()
                        //             ->acceptedFileTypes(['application/pdf'])
                        //             ->rules('mimes:pdf')
                        //             ->getUploadedFileUrlUsing(function ($file) {
                        //                 $disk = config('filesystems.default');
                        //                 if (config('filesystems.default') === 's3') {
                        //                     $disk = 's3_public';
                        //                 }
                        //                 return Storage::disk($disk)->url($file);
                        //             }),
                        //     ])
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
                // created_at
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->date('d/m/Y h:ia')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Merchant Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('brand_name')
                    ->label('Brand Name')
                    ->sortable()
                    ->searchable(),
                // Tables\Columns\TextColumn::make('user.name')
                //     ->formatStateUsing(function ($record) {
                //         return $record->has_auto_linked_user ? $record->user->name. ' (Auto Linked)' : $record->user->name;
                //     })
                //     ->searchable()
                //     ->url(fn ($record) => route('filament.resources.users.view', $record->user))
                //     ->label('Linked User Account'),
                /**
                 * Add the checking to prevent error due to the new merchant register from merchant portal won't have the user id
                 **/
                Tables\Columns\TextColumn::make('user.name')
                    ->formatStateUsing(function ($record) {
                        return $record->user 
                            ? ($record->has_auto_linked_user 
                                ? $record->user->name . ' (Auto Linked)' 
                                : $record->user->name)
                            : 'No Linked User';
                    })
                    ->searchable()
                    ->url(fn ($record) => $record->user ? route('filament.resources.users.view', $record->user) : null)
                    ->label('Linked User Account'),
                Tables\Columns\TextColumn::make('business_name'),
                Tables\Columns\TextColumn::make('redeem_code'),
                Tables\Columns\TextColumn::make('business_phone_no'),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('address_postcode'),
                Tables\Columns\TextColumn::make('pic_name'),
                Tables\Columns\TextColumn::make('pic_phone_no'),
                Tables\Columns\TextColumn::make('pic_email'),
				Tables\Columns\ToggleColumn::make('is_closed'),
			])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // reset password
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->requiresConfirmation()
                    ->action(function (Merchant $record) {
                        $record->update(['default_password' => Str::random(8)]);
                        // update record->user as well
                        $record->user->password = bcrypt($record->default_password);
                        $record->user->save();

                        Log::info('[MerchantResource] Reset Password for Merchant ID: '.$record->id. ', triggered by user: '.auth()->user()->id);

                        // resent user
                        // $record->user->notify(new MerchantOnboardEmail($record->name, $record->user->email, $record->default_password, $record->redeem_code));

                        Notification::make()
                            ->success()
                            ->title('Reset Password for Merchant ID: '.$record->id)
                            ->send();
                    }),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
                BulkAction::make('sendLoginEmail')
                    ->label('Send Merchant User Login Email')
                    ->action(function (Collection $records) {
                        $merchantIds = $records->pluck('id')->toArray();

                        if(!empty($merchantIds)){
                            //  As this action will link use the email server, try to limit the email sent per time.
                            if(count($merchantIds) > 10){
                                Notification::make()
                                    ->danger()
                                    ->title('Email Sending Limit Reached')
                                    ->body('A maximum of 10 accounts can be processed at a time to prevent excessive email sending and cause the email server been block.')
                                    ->send();

                            }else{
                                //  Send this to merchant portal api to send email.
                                $syncMerchantPortal = app(SyncMerchantPortal::class);
                                $response = $syncMerchantPortal->sendLoginEmail($merchantIds);
                                if($response['error'] == true){
                                    Notification::make()
                                    ->danger()
                                    ->title('Send email error')
                                    ->body($response['message'])
                                    ->send();
                                }else{
                                    Notification::make()
                                    ->success()
                                    ->title('Send email success')
                                    ->body($response['message'])
                                    ->send();
                              
                                }
                            }   
                            
                        }
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

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
                            if ($record->user && $record->user->email) {
                                // if merchant has an associated user with email
                                $record->user->notify(new MerchantOnboardEmail(
                                    $record->name,
                                    $record->user->email,
                                    $record->default_password,
                                    $record->redeem_code
                                ));

                                Notification::make()
                                    ->title('Sent to ' . $record->user->email)
                                    ->success()
                                    ->send();

                            } else if ($record->email) {
                                \Illuminate\Support\Facades\Notification::route('mail', $record->email)
                                    ->notify(new MerchantOnboardEmail(
                                        $record->name,
                                        $record->email,
                                        $record->default_password,
                                        $record->redeem_code
                                    ));
                            } else {
                                // log error if no email found
                                Log::error('[MerchantResource] Email not found for merchant id: ' . $record->id);
                                Notification::make()
                                    ->danger()
                                    ->title('Email not found for merchant ID: ' . $record->id)
                                    ->send();
                                return;
                            }
                        }
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('Approve Merchant')
                    ->label('Approve Merchant')
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            // must have company photos and logos
                            if (!$record->getMedia(Merchant::MEDIA_COLLECTION_NAME)->first()
                                 || !$record->getMedia(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)->first()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Merchant must have company photos and logos first to approve')
                                    ->send();

                                continue;
                            }

                            $record->update(['status' => Merchant::STATUS_APPROVED]);

                            if (empty($record->default_password)) {
                                $record->default_password = Str::random(8);
                                $record->save();

                                $user = $record->user;
                                $user->password = bcrypt($record->default_password);
                                $user->save();
                            }

                            // Send approval signal to merchant portal
                            $syncMerchantPortal = app(SyncMerchantPortal::class);
                            $syncMerchantPortal->approve($record->id);
                            $syncMerchantPortal->syncMerchant($record->id);
                            
                            // $record->user->notify(new MerchantOnboardEmail($record->name, $record->user->email, $record->default_password, $record->redeem_code));

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

                                // Send reject and sync signal to merchant portal
                                $syncMerchantPortal = app(SyncMerchantPortal::class);
                                $syncMerchantPortal->reject($record->id);

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
