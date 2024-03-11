<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\State;
use App\Models\Country;
use Filament\Pages\Page;
use App\Models\PointLedger;
use App\Models\Transaction;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;
use App\Models\MerchantOfferClaim;
use Filament\Forms\Components\Tabs;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use App\Filament\Resources\UserResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;

class UserDetails extends Page implements HasForms
{
    use InteractsWithForms;
    protected $user;
    public $pointLedgerData;
    public $offerPuchasedData;
    public $transactionData;
    public $auditData;
    protected static string $resource = UserResource::class;
    protected static ?string $model = User::class;
    // protected static ?string $navigationIcon = 'heroicon-o-user';
    // protected static ?string $navigationGroup = 'Users';
    // protected static ?string $title = 'User Details';
    // protected static ?string $navigationLabel = 'User Details';
    // protected static ?string $slug = 'user-details';
    // protected static string $view = 'filament.pages.user-details';

    public function mount(): void
    {
        // Get user's details
        $this->user = auth()->user();
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

    public function submit()
    {
        $user_id = auth()->user()->id;
        try {
            $user = User::findOrFail($user_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Notification::make()
                ->title('Error occurred while updating user details. Please try again later.')
                ->warning()
                ->send();
            return response()->json(['error' => 'User not found.'], 404);
        }

        $data = $this->validate([
            'name' => 'required',
            'username' => 'required',
            'email' => 'required|email',
            'phone_country_code' => 'numeric',
            'phone_no' => 'numeric',
        ]);

        //data for user details
        $user_details_data = [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone_country_code' => $this->phone_country_code,
            'phone_no' => $this->phone_no,
            'bio' => $this->bio,
            'dob' => $this->dob,
            'gender' => $this->gender,
            'job_title' => $this->job_title,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
        ];

        // Check if any field in personal details has changed
        $is_user_details_changed = false;
        foreach ($user_details_data as $key => $value) {
            if ($user->$key != $value) {
                $is_user_details_changed = true;
                break;
            }
        }

        // Update personal details if changed
        $user_details_updated = false;
        if ($is_user_details_changed) {
            try {
                $user->update($user_details_data);
                $user_details_updated = true;
            } catch (\Exception $e) {
                Log::error('Failed to update personal details.' . $e->getMessage());
            }
        }

        //data for login credentials
        $user_login_old_password = $this->old_password;
        $user_login_new_password = $this->new_password;
        $user_login_password_confirmation = $this->password_confirmation;

        //check if has new password ->save login credentials section
        $password_updated = false;
        if ($user_login_old_password != null || $user_login_new_password != null || $user_login_password_confirmation != null) {
            //check if old password is correct
            $user_password = $user->password;
            if (Hash::check($user_login_old_password, $user_password)) {
                // If the old password match, check if new password and confirm password is the same
                if ($user_login_new_password != $user_login_password_confirmation) {
                    Notification::make()
                        ->title('New password and confirm password is not the same.')
                        ->danger()
                        ->send();
                    return redirect()->route('filament.pages.user-details');
                }
                //check if new password is the same as old password
                if ($user_login_new_password == $user_login_old_password) {
                    Notification::make()
                        ->title('New password cannot be the same as old password.')
                        ->danger()
                        ->send();
                    return redirect()->route('filament.pages.user-details');
                }
                //check if new password is at least 8 characters
                if (strlen($user_login_new_password) < 8) {
                    Notification::make()
                        ->title('New password must be at least 8 characters.')
                        ->danger()
                        ->send();
                    return redirect()->route('filament.pages.user-details');
                }
                //check if new password is alphanumeric
                if (!ctype_alnum($user_login_new_password)) {
                    Notification::make()
                        ->title('New password must be alphanumeric.')
                        ->danger()
                        ->send();
                    return redirect()->route('filament.pages.user-details');
                }

                //proceed to update password
                $user->password = Hash::make($user_login_new_password);
                $password_updated = true;
                $user->save();
            } else {
                Notification::make()
                    ->title('Old password is incorrect.')
                    ->danger()
                    ->send();
                return redirect()->route('filament.pages.user-details');
            }
        }

        // Send appropriate notification based on updates
        if ($user_details_updated && $password_updated) {
            // Both user details and password updated successfully
            Notification::make()
                ->title('User details have been updated successfully!')
                ->success()
                ->send();
        } elseif ($user_details_updated || $password_updated) {
            // Only one of the sections updated successfully
            Notification::make()
                ->title('User details have been updated successfully!')
                ->success()
                ->send();
        } else {
            // Both sections failed to update
            Notification::make()
                ->title('Failed to update user details. Please try again.')
                ->danger()
                ->send();
        }
    }

    protected function getPointLedgerData()
    {
        return PointLedger::where('user_id', auth()->id())->get();
    }

    protected function getOfferPuchasedData()
    {
        return MerchantOfferClaim::where('user_id', auth()->id())->get();
    }

    protected function getTransactionData()
    {
        return Transaction::where('user_id', auth()->id())->get();
    }

    protected function getAuditData()
    {
        return Audit::where('user_id', auth()->id())->get();
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
                                                    ->autofocus()
                                                    ->required()
                                                    ->rules('required', 'max:255'),
                                                TextInput::make('username')
                                                    ->required()
                                                    // transform lowercaser and remove spaces
                                                    ->afterStateHydrated(function ($component, $state) {
                                                        $component->state(Str::slug($state));
                                                    })
                                                    ->rules('required', 'max:255', 'unique:users,username'),
                                                TextInput::make('job_title')
                                                    ->rules('nullable', 'max:255'),
                                                Fieldset::make('Phone Number')
                                                    ->schema([
                                                        TextInput::make('phone_country_code')
                                                            ->placeholder('60')
                                                            ->label('Country Code')
                                                            ->afterStateHydrated(function ($component, $state) {
                                                                // ensure no symbols only numbers
                                                                $component->state(preg_replace('/[^0-9]/', '', $state));
                                                            })
                                                            ->rules('nullable', 'max:255')->columnSpan(['lg' => 1]),
                                                        TextInput::make('phone_no')
                                                            ->placeholder('eg. 123456789')
                                                            ->label('Phone Number')
                                                            ->afterStateHydrated(function ($component, $state) {
                                                                // ensure no symbols only numbers
                                                                $component->state(preg_replace('/[^0-9]/', '', $state));
                                                            })
                                                            ->rules('nullable', 'max:255')
                                                            ->columnSpan(['lg' => 1]),
                                                    ])->columns(2),
                                                Radio::make('gender')
                                                    ->inline()
                                                    ->options([
                                                        'male' => 'Male',
                                                        'female' => 'Female'
                                                    ])
                                                    ->rules('nullable'),
                                            ])->columnSpan(['lg' => 2]),

                                        Group::make()
                                            ->schema([
                                                DatePicker::make('dob')
                                                    ->label('Date of Birth')
                                                    ->rules('nullable'),
                                                TextInput::make('email')
                                                    ->rules('required', 'email', 'max:255', 'unique:users,email'),
                                                Select::make('status')
                                                    ->options([
                                                        1 => 'Active',
                                                        2 => 'Suspended',
                                                        3 => 'Archived',
                                                    ])
                                                    ->disabled(),
                                                Fieldset::make('Location')
                                                    ->schema([
                                                        Select::make('country_id')
                                                            ->label('Country')
                                                            ->options(Country::all()->pluck('name', 'id')->toArray())
                                                            ->nullable()
                                                            ->rules('nullable'),
                                                        Select::make('state_id')
                                                            ->label('State')
                                                            ->options(State::all()->pluck('name', 'id')->toArray())
                                                            ->nullable()
                                                            ->rules('nullable'),
                                                    ])
                                            ])->columnSpan(['lg' => 2]),

                                        Group::make()
                                            ->schema([
                                                Textarea::make('bio')
                                                    ->rules('nullable', 'max:255'),
                                            ])->columnSpanFull(),
                                    ])->columns(['lg' => 4]),

                                Section::make('Login Credentials')
                                    ->schema([
                                        Group::make()
                                            ->schema([
                                                TextInput::make('old_password')
                                                    ->label('Old Password')
                                                    ->password()
                                                    ->required()
                                                    ->placeholder(''),
                                                TextInput::make('new_password')
                                                    ->label('New Password')
                                                    ->password()
                                                    ->required()
                                                    ->placeholder(''),
                                                TextInput::make('password_confirmation')
                                                    ->label('Confirm New Password')
                                                    ->password()
                                                    ->required()
                                                    ->placeholder(''),
                                            ])
                                    ]),

                                Section::make('Roles')
                                    ->schema([
                                        TextInput::make('user_roles')
                                            ->disabled()
                                    ]),

                                ViewField::make('details_save_button')
                                    ->view('livewire.save-button')
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
}
