<?php

namespace App\Http\Livewire;

use App\Models\Country;
use App\Models\Merchant;
use App\Models\User;
use App\Models\State;
use App\Models\Store;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Closure;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Http\Request;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Pages\Actions\Action;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;

class MerchantRegister extends Component implements HasForms
{
    use InteractsWithForms;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function register(Request $request)
    {
        //dd(array_values($this->company_logo)[0]->getRealPath());
        $data = $this->validate([
            'business_name' => 'required',
            'company_reg_no' => 'required',
            'brand_name' => 'required',
            'phone_country_code' => 'required',
            'business_phone_no' => 'required|unique:users,phone_no',
            'address' => 'required',
            'company_logo' => 'required',
            'company_photos' => 'required',
            'auto_complete_address' => 'nullable',
            'location' => 'nullable',
            'zip_code' => 'required|numeric',
            'state_id' => 'nullable',
            'country_id' => 'nullable',
            'pic_name' => 'required',
            'pic_designation' => 'required',
            'pic_ic_no' => 'required|numeric',
            'pic_phone_no' => 'required',
            'pic_email' => 'required|email',
            // 'stores' => 'required',
            // 'stores.*.name' => 'required',
            // 'stores.*.is_hq' => 'boolean',
            // 'stores.*.manager_name' => 'required',
            // 'stores.*.business_phone_no' => 'required',
            // 'stores.*.address' => 'required',
            // 'stores.*.zip_code' => 'required|numeric',
            // 'stores.*.business_hours' => 'required',
            // 'stores.*.business_hours.*.day' => 'required',
            // 'stores.*.business_hours.*.open_time' => 'required',
            // 'stores.*.business_hours.*.close_time' => 'required',
            'name' => 'required',
            'is_hq' => 'boolean',
            'manager_name' => 'required',
            'business_phone_no' => 'required|unique:users,phone_no',
            'address' => 'required',
            'zip_code' => 'required|numeric',
            'business_hours' => 'required',
            'business_hours.*.day' => 'required',
            'business_hours.*.open_time' => 'required',
            'business_hours.*.close_time' => 'required',
            'company_email' => 'required|email|unique:users,email',
            'password' => 'required',
            'passwordConfirmation' => 'required|same:password',
        ]);

        //check only 1 store is hq
        // $hq_count = 0;
        // foreach ($data['stores'] as $store) {
        //     if ($store['is_hq']) {
        //         $hq_count++;
        //     }
        // }
        // if ($hq_count != 1) {
        //     session()->flash('error', 'Please select only 1 store as HQ.');
        // }

        //create user using the company_email and password
        $user = null;
        try {
            $user = User::create([
                'name' => $data['brand_name'],
                'email' => $data['company_email'],
                'phone_no' => $data['business_phone_no'],
                'phone_country_code' => $data['phone_country_code'],
                'password' => bcrypt($data['password']),
            ]);
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] User creation failed: ' . $e->getMessage());
            session()->flash('error', 'User creation failed. Please try again.');
        }

        if (!$user) {
            session()->flash('error', 'User creation failed. Please try again.');
            return;
        }

        //assign merchant role to the user
        try {
            $user->assignRole('merchant');
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] User role assignment failed: ' . $e->getMessage());
            session()->flash('error', 'User role assignment failed. Please try again.');
        }
        //create merchant using the data from the form and user_id
        //brand name -> name (eg. Nedex Solutions)
        //company name -> business name  (eg. NEDEX GROUP SDN BHD)
        $merchant = null;
        try {
            $merchant = Merchant::create([
                'user_id' => $user->id,
                'name' => $data['brand_name'],
                'email' => $data['company_email'],
                'business_name' => $data['business_name'],
                'company_reg_no' => $data['company_reg_no'],
                'business_phone_no' => $data['phone_country_code'].$data['business_phone_no'],
                'address' => $data['address'],
                'address_postcode' => $data['zip_code'],
                'state_id' => $data['state_id'],
                'country_id' => $data['country_id'],
                'pic_name' => $data['pic_name'],
                'pic_designation' => $data['pic_designation'],
                'pic_ic_no' => $data['pic_ic_no'],
                'pic_phone_no' => $data['pic_phone_no'],
                'pic_email' => $data['pic_email'],
            ]);
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] Merchant creation failed: ' . $e->getMessage());
            session()->flash('error', 'Merchant creation failed. Please try again.');
        }

        if (!$merchant) {
            session()->flash('error', 'Merchant creation failed. Please try again.');
            return;
        }

        //save the company logo to the merchant's media collection
        //dd($data['company_logo']);
        try {
            $company_logo_livewire_tmp = array_values($data['company_logo'])[0];
            Log::info($company_logo_livewire_tmp);
            Log::info('[MerchantOnboarding] Company logo upload: ' . $company_logo_livewire_tmp->getRealPath());
            $merchant->addMediaFromDisk($company_logo_livewire_tmp->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME);
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] Company logo upload failed: ' . $e->getMessage());
            session()->flash('error', 'Company logo upload failed. Please try again.');
        }

        //save the company photos to the merchant's media collection
        try {
            foreach ($data['company_photos'] as $company_photo) {
                $company_photo_livewire_tmp = $company_photo;
                Log::info($company_photo_livewire_tmp);
                Log::info('[MerchantOnboarding] Company photo upload: ' . $company_photo_livewire_tmp->getRealPath());
                $merchant->addMediaFromDisk($company_photo_livewire_tmp->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                    ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
            }
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] Company photos upload failed: ' . $e->getMessage());
            session()->flash('error', 'Company photos upload failed. Please try again.');
        }

        $merchant->save();

        //create store using the data from the form and user_id
        try {
            // foreach ($data['stores'] as $store) {
                //process business hours
                $businessHours = [];
                foreach ($data['business_hours'] as $businessHour) {
                    $businessHours[$businessHour['day']] = [
                        'open_time' => \Carbon\Carbon::parse($businessHour['open_time'])->format('H:i'),
                        'close_time' => \Carbon\Carbon::parse($businessHour['close_time'])->format('H:i'),
                    ];
                }

                $store = Store::create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'business_phone_no' => $data['business_phone_no'],
                    'business_hours' => json_encode($businessHours),
                    'address' => $data['address'],
                    'address_postcode' => $data['zip_code'],
                    'lang' => $data['location']['lat'],
                    'long' => $data['location']['lng'],
                    'is_hq' => $data['is_hq'],
                    'state_id' => $data['state_id'],
                    'country_id' => $data['country_id'],
                ]);

                // $store = Store::create([
                //     'user_id' => $user->id,
                //     'name' => $store['name'],
                //     'manager_name' => $store['manager_name'],
                //     'business_phone_no' => $store['business_phone_no'],
                //     'business_hours' => json_encode($businessHours),
                //     'address' => $store['address'],
                //     'address_postcode' => $store['zip_code'],
                //     'lang' => $data['location']['lat'],
                //     'long' => $data['location']['lng'],
                //     'is_hq' => $store['is_hq'],
                //     'state_id' => $data['state_id'],
                //     'country_id' => $data['country_id'],
                // ]);
            // }
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] Store creation failed: ' . $e->getMessage());
            session()->flash('error', 'Store creation failed. Please try again.');
        }

        if ($user && $merchant) {
            session()->flash('message', 'Your Merchant Account has been created. Please await our admins to approve your account and once approved you will received the instructions to login. Thank you!');
            return redirect()->route('merchant.register');
        }

    }

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Company')
                    ->schema([
                        TextInput::make('business_name') //merchant's table 'business_name'
                        ->label('Company Name (as per SSM)')
                        ->required()
                        ->placeholder('Enter Company Name'),
                        TextInput::make('company_reg_no') //merchant's table new column 'company_reg_no'
                        ->label('Registration Number')
                        ->required()
                        ->placeholder('Enter Registration Number'),
                        TextInput::make('brand_name') //merchant's table new column 'brand_name'
                        ->label('Brand Name of Branches')
                        ->required()
                        ->placeholder('Enter Brand Name'),
                        // TextInput::make('business_phone_no') //merchant's table column 'business_phone_no'
                        // ->label('Contact Number')
                        // ->required()
                        // ->placeholder('Enter Contact Number'),
                        // inline phone_country_code and phone_no without labels but placeholders textinputs
                        Fieldset::make('Phone Number')
                        ->schema([
                            TextInput::make('phone_country_code')
                                ->placeholder('60')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                })
                                ->rules('required', 'max:255')->columnSpan(['lg' => 1]),
                            TextInput::make('business_phone_no')
                                ->placeholder('eg. 123456789')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                })
                                ->helperText('This phone number will be used to create an account on Funhub. Cannot reuse existing Funhub account registered phone numbers')
                                //->unique(table: User::class, column: 'phone_no')
                                ->rules(['required', 'max:255', 'unique:users,phone_no'])
                                ->columnSpan(['lg' => 3]),
                        ])->columns(4),
                        TextInput::make('address') //merchant's table 'address'
                        ->label('Company Address')
                        ->required()
                        ->placeholder('Enter Location'),

                        Group::make([
                            Section::make('Location Details')
                                ->schema([
                                    TextInput::make('auto_complete_address')
                                        ->label('Find a Location')
                                        ->placeholder('Start typing an address ...'),

                                    Map::make('location')
                                        ->autocomplete(
                                            fieldName: 'auto_complete_address',
                                            placeField: 'name',
                                            countries: ['MY'],
                                        )
                                        ->reactive()
                                        ->defaultZoom(15)
                                        ->defaultLocation([
                                            // klang valley coordinates
                                            'lat' => 3.1390,
                                            'lng' => 101.6869,
                                        ])
                                        ->reverseGeocode([
                                            'city'   => '%L',
                                            'zip'    => '%z',
                                            'state'  => '%D',
                                            'zip_code' => '%z',
                                            'address' => '%n %S',
                                        ])
                                        ->mapControls([
                                            'mapTypeControl'    => true,
                                            'scaleControl'      => true,
                                            'streetViewControl' => false,
                                            'rotateControl'     => true,
                                            'fullscreenControl' => true,
                                            'searchBoxControl'  => false, // creates geocomplete field inside map
                                            'zoomControl'       => false,
                                        ])
                                        ->clickable(true),

                                    TextInput::make('address')
                                        ->required(),
                                    TextInput::make('zip_code') //merchant's table 'address_postcode'
                                        ->rules('numeric')
                                        ->label('Postcode')
                                        ->required(),
                                    Select::make('state_id') //merchant's table 'state_id'
                                        ->label('State')
                                        ->options(State::all()->pluck('name', 'id')->toArray()),
                                    Select::make('country_id') //merchant's table 'country_id'
                                        ->label('Country')
                                        ->default(131)
                                        ->options(Country::all()->pluck('name', 'id')->toArray()),
                                ])
                        ])->columnSpan(['lg' => 1]),
                        SpatieMediaLibraryFileUpload::make('company_logo')
                        ->label('Company Logo')
                        ->maxFiles(1)
                        ->collection(Merchant::MEDIA_COLLECTION_NAME)
                        ->required()
                        ->columnSpan('full')
                        ->acceptedFileTypes(['image/*'])
                        ->rules('image'),
                        SpatieMediaLibraryFileUpload::make('company_photos')
                        ->label('Company Photos')
                        ->multiple()
                        ->maxFiles(7)
                        ->collection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)
                        ->required()
                        ->columnSpan('full')
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
                        TextInput::make('pic_email') //merchant's table column 'pic_email'
                        ->label('PIC Email')
                        ->required()
                        ->placeholder('Enter Email'),
                    ]),
                Wizard\Step::make('Store')
                    ->schema([
                        // Repeater::make('stores')
                        //     ->schema([
                                TextInput::make('name') //stores table 'name'
                                ->required()
                                ->label('Store Name')
                                ->columnSpan('full')
                                ->placeholder('Enter Store Name'),
                                Toggle::make('is_hq')
                                ->label('Is Headquarters?')
                                ->columnSpan('full'),
                                TextInput::make('manager_name') //stores table new column 'manager_name'
                                ->label('Manager Name')
                                ->required()
                                ->placeholder('Enter Manager Name'),
                                TextInput::make('business_phone_no') //stores table column 'business_phone_no'
                                ->label('Contact Number')
                                ->required()
                                ->placeholder('Enter Contact Number'),
                                // TextInput::make('address') //stores table 'address'
                                // ->label('Store Address')
                                // ->required()
                                // ->placeholder('Enter Location')
                                // ->columnSpan('full'),
                                // TextInput::make('address_postcode') //stores table 'address_postcode'
                                // ->label('Store Address Postcode')
                                // ->required()
                                // ->placeholder('Enter Store Address Postcode')
                                // ->columnSpan('full'),
                                Group::make([
                                    Section::make('Location Details')
                                        ->schema([
                                            TextInput::make('auto_complete_address')
                                                ->label('Find a Location')
                                                ->placeholder('Start typing an address ...'),

                                            Map::make('location')
                                                ->autocomplete(
                                                    fieldName: 'auto_complete_address',
                                                    placeField: 'name',
                                                    countries: ['MY'],
                                                )
                                                ->reactive()
                                                ->defaultZoom(15)
                                                ->defaultLocation([
                                                    // klang valley coordinates
                                                    'lat' => 3.1390,
                                                    'lng' => 101.6869,
                                                ])
                                                ->reverseGeocode([
                                                    'city'   => '%L',
                                                    'zip'    => '%z',
                                                    'state'  => '%D',
                                                    'zip_code' => '%z',
                                                    'address' => '%n %S',
                                                ])
                                                ->mapControls([
                                                    'mapTypeControl'    => true,
                                                    'scaleControl'      => true,
                                                    'streetViewControl' => false,
                                                    'rotateControl'     => true,
                                                    'fullscreenControl' => true,
                                                    'searchBoxControl'  => false, // creates geocomplete field inside map
                                                    'zoomControl'       => false,
                                                ])
                                                ->clickable(true),

                                            TextInput::make('address')
                                                ->required(),
                                            TextInput::make('zip_code') //stores table 'address_postcode'
                                                ->rules('numeric')
                                                ->label('Postcode')
                                                ->required(),
                                            Select::make('state_id') //stores table 'state_id'
                                                ->label('State')
                                                ->options(State::all()->pluck('name', 'id')->toArray()),
                                            Select::make('country_id') //stores table 'country_id'
                                                ->label('Country')
                                                ->default(131)
                                                ->options(Country::all()->pluck('name', 'id')->toArray()),
                                        ])
                                ])->columnSpan('full'),
                                Repeater::make('business_hours') //stores table new column 'business_hours'(json)
                                    ->schema([
                                        Select::make('day')
                                            ->options([
                                                '1' => 'Monday',
                                                '2' => 'Tuesday',
                                                '3' => 'Wednesday',
                                                '4' => 'Thursday',
                                                '5' => 'Friday',
                                                '6' => 'Saturday',
                                                '7' => 'Sunday',
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
                                                    ->default('09:00')
                                                    ->label('Open Time'),
                                                TimePicker::make('close_time')
                                                    ->withoutSeconds()
                                                    ->withoutDate()
                                                    ->required()
                                                    ->default('17:00')
                                                    ->label('Close Time'),
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->columnSpan('full'),
                            // ])
                            // ->columns(2)
                    ]),
                Wizard\Step::make('Login')
                    ->schema([
                        TextInput::make('company_email') //users table and merchant's table column 'email'
                        ->label('Company Email')
                        ->required()
                        ->placeholder('Enter Email'),
                        TextInput::make('password') //users table column 'password'
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
            ->skippable()
            ->submitAction(new HtmlString('<button type="submit" class="filament-button filament-button-size-sm inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-3 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Complete Signup</button>')),
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

