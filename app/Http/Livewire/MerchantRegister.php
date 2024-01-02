<?php

namespace App\Http\Livewire;

use App\Models\Merchant;
use Closure;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Http\Request;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Wizard;

class MerchantRegister extends Component implements HasForms
{
    use InteractsWithForms;
    
    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Company')
                    ->schema([
                        TextInput::make('business_name') //merchant's table 'business_name'
                        ->label('Company Name')
                        ->required()
                        ->placeholder('Enter Company Name'),
                        TextInput::make('registration_no') //merchant's table new column 'company_reg_no'
                        ->label('Registration Number')
                        ->required()
                        ->placeholder('Enter Registration Number'),
                        TextInput::make('brand_name') //merchant's table new column 'brand_name'
                        ->label('Brand Name of Branches')
                        ->required()
                        ->placeholder('Enter Brand Name'),
                        TextInput::make('address') //merchant's table 'address'
                        ->label('Company Address')
                        ->required()
                        ->placeholder('Enter Location'),
                        TextInput::make('address_postcode') //merchant's table 'address_postcode'
                        ->rules('numeric')
                        ->label('Postcode')
                        ->required()
                        ->placeholder('Enter Company Address Postcode'),
                        // Select::make('state_id') //merchant's table 'state_id'
                        //     ->label('State')
                        //     ->required()
                        //     ->relationship('state', 'name'),
                        // Select::make('country_id') //merchant's table 'coutry_id'
                        //     ->label('Country')
                        //     ->default(131)
                        //     ->required()
                        //     ->relationship('country', 'name'),
                        SpatieMediaLibraryFileUpload::make('company_logo')
                        ->label('Company Logo')
                        ->maxFiles(1)
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
                Wizard\Step::make('PIC')
                    ->schema([
                        TextInput::make('pic_name') //merchant's table 'pic_name'
                        ->label('PIC Name')
                        ->required()
                        ->placeholder('Enter PIC Name'),
                        TextInput::make('pic_designation') //merchant's table new column 'pic_designation'
                        ->label('Designation')
                        ->required()
                        ->placeholder('Enter Designation'),
                        TextInput::make('pic_ic_no') //merchant's table new column 'pic_ic_no'
                        ->label('IC Number')
                        ->required()
                        ->placeholder('Enter IC Number'),
                        TextInput::make('pic_phone_no') //merchant's table column 'pic_phone_no'
                        ->label('Contact Number')
                        ->required()
                        ->placeholder('Enter Contact Number'),
                        TextInput::make('email') //merchant's table column 'pic_email'
                        ->label('PIC Email')
                        ->required()
                        ->placeholder('Enter Email'),
                    ]),
                Wizard\Step::make('Store')
                    ->schema([
                        Repeater::make('Stores')
                            ->schema([
                                TextInput::make('name') //stores table 'name'
                                ->required()
                                ->label('Store Name')
                                ->columnSpan('full')
                                ->placeholder('Enter Store Name'),
                                TextInput::make('manager_name') //stores table new column 'manager_name'
                                ->label('Manager Name')
                                ->required()
                                ->placeholder('Enter Manager Name'),
                                TextInput::make('manager_contact_no') //stores table new column 'manager_contact_no'
                                ->label('Contact Number')
                                ->required()
                                ->placeholder('Enter Contact Number'),
                                TextInput::make('address') //stores table 'address'
                                ->label('Store Address')
                                ->required()
                                ->placeholder('Enter Location')
                                ->columnSpan('full'),
                                TextInput::make('address_postcode') //stores table 'address_postcode'
                                ->label('Store Address Postcode')
                                ->required()
                                ->placeholder('Enter Store Address Postcode')
                                ->columnSpan('full'),
                                Repeater::make('Business Hours')
                                    ->schema([
                                        Select::make('day')
                                            ->options([
                                                'Monday' => 'Monday',
                                                'Tuesday' => 'Tuesday',
                                                'Wednesday' => 'Wednesday',
                                                'Thursday' => 'Thursday',
                                                'Friday' => 'Friday',
                                                'Saturday' => 'Saturday',
                                                'Sunday' => 'Sunday',
                                            ])
                                            ->required()
                                            ->label('Day')
                                            ->columnSpan('full'),
                                            Grid::make(2)
                                            ->schema([
                                                TimePicker::make('open_time')
                                                    ->withoutSeconds()
                                                    ->withoutDate()
                                                    ->required()
                                                    ->default(function ($record) {
                                                        if ($record) {
                                                            return $record->opening_hours['open_time'];
                                                        } else {
                                                            return '09:00';
                                                        }
                                                    })
                                                    ->label('Open Time'),
                                                TimePicker::make('close_time')
                                                    ->withoutSeconds()
                                                    ->withoutDate()
                                                    ->required()
                                                    ->default(function ($record) {
                                                        if ($record) {
                                                            return $record->opening_hours['close_time'];
                                                        } else {
                                                            return '17:00';
                                                        }
                                                    })
                                                    ->label('Close Time'),
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->columnSpan('full'),
                            ])
                            ->columns(2)
                    ]),
                Wizard\Step::make('Login')
                    ->schema([
                        TextInput::make('company_email')
                        ->label('Company Email')
                        ->required()
                        ->placeholder('Enter Email'),
                        TextInput::make('password')
                        ->password()
                        ->required()
                        ->label('Password')
                        ->placeholder('Enter Password'),
                        TextInput::make('passwordConfirmation')
                        ->password()
                        ->required()
                        ->label('Confirm Password')
                        ->placeholder('Confirm Password'),
                    ]),
            ])
            ->skippable(),
                ];
    }

    public function render()
    {
        return view('livewire.merchant-register')
                ->layout('filament::components.layouts.base', [
                    'title' => 'Register as Merchant',
                ]);
    }
}

