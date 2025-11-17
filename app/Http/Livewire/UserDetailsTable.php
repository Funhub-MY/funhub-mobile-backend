<?php

namespace App\Http\Livewire;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use App\Models\User;
use App\Models\State;
use App\Models\Country;
use Livewire\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Database\Eloquent\Relations\Relation;

class UserDetailsTable extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $user;
    public $currentRouteId;

    public function render()
    {
        return view('livewire.user-details-table');
    }

    public function mount($currentRouteId)
    {
        $this->currentRouteId = $currentRouteId;

        // Get record's user details
        $this->user = User::where('id', $this->currentRouteId)->first();
        $user_attributes = $this->user->getAttributes();
        $user_roles = $this->user->roles?->pluck('name')->toArray();
        $user_roles = implode(', ', $user_roles);

        $this->form->fill(
            [
                'name' => $user_attributes['name'],
                'username' => $user_attributes['username'],
                'email' => $user_attributes['email'],
                'email_verified_at' => $user_attributes['email_verified_at'],
                'status' => $user_attributes['status'],
                'suspended_until' => $user_attributes['suspended_until'],
                'phone_country_code' => $user_attributes['phone_country_code'],
                'phone_no' => $user_attributes['phone_no'],
                'otp_verified_at' => $user_attributes['otp_verified_at'],
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

    protected function getColumns(): int | string | array
    {
        return 2;
    }

    protected function getFormSchema(): array
    {
        return [
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
                                Select::make('status')
                                    ->options([
                                        1 => 'Active',
                                        2 => 'Suspended',
                                        3 => 'Archived',
                                    ])
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
                                DateTimePicker::make('email_verified_at')
                                    ->disabled(),
                                TextInput::make('suspended_until')
                                    ->disabled(),
                                Fieldset::make('Location')
                                    ->schema([
                                        Select::make('country_id')
                                            ->label('Country')
                                            ->options(Country::all()->pluck('name', 'id')->toArray())
                                            ->disabled(),
                                            Select::make('state_id')
                                            ->label('State')
                                            ->options(State::all()->pluck('name', 'id')->toArray())
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

                Section::make('Roles')
                    ->schema([
                        TextInput::make('user_roles')
                            ->disabled()
                    ]),
            ]),
        ];
    }

    protected function getTableQuery(): Builder|Relation
    {
        if ($this->currentRouteId) {
            return User::where('id', $this->currentRouteId);
        }
    }
}
