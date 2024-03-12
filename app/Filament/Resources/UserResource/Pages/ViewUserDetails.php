<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\User;
use App\Models\State;
use App\Models\Country;
use App\Models\PointLedger;
use App\Models\Transaction;
use Filament\Pages\Actions;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use App\Models\MerchantOfferClaim;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\UserResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;

class ViewUserDetails extends ViewRecord implements HasForms
{
    use InteractsWithForms;
    protected $user;
    public $pointLedgerData;
    public $offerPuchasedData;
    public $transactionData;
    public $auditData;
    protected static string $resource = UserResource::class;
    protected static ?string $model = User::class;
    protected static string $view = 'filament.pages.user-details';

    public function getRecord(): Model
    {
        return $this->user;
    }

    public function mount($record): void
    {
        // Get record's user details
        $this->user = User::where('id', $record)->first();
        $this->pointLedgerData = $this->getPointLedgerData();
        $this->offerPuchasedData = $this->getOfferPuchasedData();
        $this->transactionData = $this->getTransactionData();
        $this->auditData = $this->getAuditData();
        $user_attributes = $this->user->getAttributes();

        $user_roles = $this->user->roles?->pluck('name')->toArray();
        $user_roles = implode(', ', $user_roles);

        $this->form->fill(
            [
                'name' => $user_attributes['name'],
                'username' => $user_attributes['username'],
                'email' => $user_attributes['email'],
                'status' => $user_attributes['status'],
                'phone_country_code' => $user_attributes['phone_country_code'],
                'phone_no' => $user_attributes['phone_no'],
                'bio' => $user_attributes['bio'],
                'dob' => $user_attributes['dob'],
                'gender' => $user_attributes['gender'],
                'job_title' => $user_attributes['job_title'],
                'country_id' => $user_attributes['country_id'],
                'state_id' => $user_attributes['state_id'],
                'user_roles' => $user_roles
            ]
        );
    }

    protected function getPointLedgerData()
    {
        return PointLedger::where('user_id', $this->user->id)->get();
    }

    protected function getOfferPuchasedData()
    {
        return MerchantOfferClaim::where('user_id', $this->user->id)->get();
    }

    protected function getTransactionData()
    {
        return Transaction::where('user_id', $this->user->id)->get();
    }

    protected function getAuditData()
    {
        return Audit::where('user_id', $this->user->id)->get();
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Tabs')
                ->tabs([
                    Tab::make('Details')
                        ->schema([
                            Group::make([
                                Section::make('Personal Details')
                                    ->schema([
                                        Group::make()
                                            ->schema([
                                                TextInput::make('name')
                                                    ->disabled(),
                                                DatePicker::make('dob')
                                                    ->label('Date of Birth')
                                                    ->disabled(),
                                                TextInput::make('email')
                                                    ->disabled(),
                                                TextInput::make('status')
                                                    ->disabled(),
                                                Fieldset::make('Phone Number')
                                                    ->schema([
                                                        TextInput::make('phone_country_code')
                                                            ->disabled()
                                                            ->label('Country Code'),
                                                        TextInput::make('phone_no')
                                                            ->disabled()
                                                            ->label('Phone Number'),
                                                    ]),
                                                TextInput::make('otp_verified_at')
                                                    ->label('Phone No OTP Verified At')
                                                    ->disabled(),
                                            ])->columnSpan(['lg' => 2]),

                                        Group::make()
                                            ->schema([
                                                TextInput::make('username')
                                                    ->disabled(),
                                                TextInput::make('job_title')
                                                    ->disabled(),
                                                TextInput::make('email_verified_at')
                                                    ->disabled(),
                                                TextInput::make('suspended_until')
                                                    ->disabled(),
                                                Fieldset::make('Location')
                                                    ->schema([
                                                        TextInput::make('country_id')
                                                            ->label('Country')
                                                            ->disabled(),
                                                        TextInput::make('state_id')
                                                            ->label('State')
                                                            ->disabled(),
                                                    ]),
                                                Radio::make('gender')
                                                    ->inline()
                                                    ->options([
                                                        'male' => 'Male',
                                                        'female' => 'Female'
                                                    ])
                                                    ->disabled(),
                                            ])->columnSpan(['lg' => 2]),

                                        Group::make()
                                            ->schema([
                                                Textarea::make('bio')
                                                    ->disabled(),
                                            ])->columnSpanFull(),
                                    ])->columns(['lg' => 4]),

                                // Section::make('Login Credentials')
                                //     ->schema([
                                //         Group::make()
                                //             ->schema([
                                //                 TextInput::make('old_password')
                                //                     ->label('Old Password')
                                //                     ->password()
                                //                     ->required()
                                //                     ->placeholder(''),
                                //                 TextInput::make('new_password')
                                //                     ->label('New Password')
                                //                     ->password()
                                //                     ->required()
                                //                     ->placeholder(''),
                                //                 TextInput::make('password_confirmation')
                                //                     ->label('Confirm New Password')
                                //                     ->password()
                                //                     ->required()
                                //                     ->placeholder(''),
                                //             ])
                                //     ]),

                                Section::make('Roles')
                                    ->schema([
                                        TextInput::make('user_roles')
                                            ->disabled()
                                    ]),

                                // ViewField::make('details_save_button')
                                //     ->view('livewire.save-button')
                            ]),
                        ]),
                    Tab::make('Point Ledger')
                        ->schema([
                            Group::make([
                                Section::make('Point Ledger')
                                    ->schema([
                                        ViewField::make('point_ledger')
                                            ->view('livewire.user-details-point-ledger', [
                                                'pointLedgerData' => $this->pointLedgerData,
                                            ])
                                    ])
                            ])
                        ]),
                    Tab::make('Merchant Offer Purchased')
                        ->schema([
                            Group::make([
                                Section::make('Merchant Offer Purchased')
                                    ->schema([
                                        ViewField::make('merchant_offer_purchased')
                                            ->view('livewire.user-details-merchant-offer-puchased', [
                                                'offerPuchasedData' => $this->offerPuchasedData,
                                            ])
                                    ])
                            ])
                        ]),
                    Tab::make('Transaction History')
                        ->schema([
                            Group::make([
                                Section::make('Transaction History')
                                    ->schema([
                                        ViewField::make('transaction_history')
                                            ->view('livewire.user-details-transaction-history', [
                                                'transactionData' => $this->transactionData,
                                            ])
                                    ])
                            ])
                        ]),
                    Tab::make('Audit Log')
                        ->schema([
                            Group::make([
                                Section::make('Audit Log')
                                    ->schema([
                                        ViewField::make('audit_log')
                                            ->view('livewire.user-details-audit-log', [
                                                'auditData' => $this->auditData,
                                            ])
                                    ])
                            ])
                        ]),
                ]),
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
