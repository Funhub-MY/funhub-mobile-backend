<?php

namespace App\Http\Livewire;

use App\Models\Country;
use App\Models\Location;
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
use Filament\Forms\Components\Placeholder;
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
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            // 'company_logo' => 'required',
            // 'company_photos' => 'required',
            // 'auto_complete_address' => 'nullable',
            // 'location' => 'nullable',
            'zip_code' => 'required|numeric',
            'state_id' => 'required',
            'country_id' => 'required',
            // 'pic_name' => 'required',
            // 'pic_designation' => 'required',
            // 'pic_ic_no' => 'required|numeric',
            // 'pic_phone_no' => 'required',
            // 'pic_email' => 'required_with:pic_name|email',
            'authorised_personnel_name' => 'required',
            'authorised_personnel_designation' => 'required',
            'authorised_personnel_ic_no' => 'required',
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
            // 'name' => 'required',
            // 'is_hq' => 'boolean',
            // 'manager_name' => 'required',
            // 'store_business_phone_no' => 'required',
            'address' => 'required',
            'zip_code' => 'required|numeric',
            // 'business_hours' => 'required',
            // 'business_hours.*.day' => 'required',
            // 'business_hours.*.open_time' => 'required',
            // 'business_hours.*.close_time' => 'required',
            'company_email' => 'required|email|unique:users,email',
            // 'password' => 'required',
            // 'passwordConfirmation' => 'required|same:password',
        ]);

        //create user using the company_email and password
        $user = null;

        // create a default password
        $password = Str::random(8);

        try {
            $user = User::create([
                'name' => $data['brand_name'],
                'email' => $data['company_email'],
                'phone_no' => $data['business_phone_no'],
                'phone_country_code' => $data['phone_country_code'],
                'password' => bcrypt($password),
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
                'pic_name' => isset($data['pic_name']) ? $data['pic_name'] : null,
                'pic_designation' => isset($data['pic_designation']) ? $data['pic_designation'] : null,
                'pic_ic_no' => isset($data['pic_ic_no']) ? $data['pic_ic_no'] : null,
                'pic_phone_no' => isset($data['pic_phone_no']) ? $data['pic_phone_no'] : null,
                'pic_email' => isset($data['pic_email']) ? $data['pic_email'] : null,
                'authorised_personnel_name' => isset($data['authorised_personnel_name']) ? $data['authorised_personnel_name'] : null,
                'authorised_personnel_designation' => isset($data['authorised_personnel_designation']) ? $data['authorised_personnel_designation'] : null,
                'authorised_personnel_ic_no' => isset($data['authorised_personnel_ic_no']) ? $data['authorised_personnel_ic_no'] : null,
                'default_password' => $password,
            ]);

            if ($merchant) {
                // ensure redeem code is unique loop
                $maxTries = 0;
                $data['redeem_code'] = rand(100000, 999999);
                while (Merchant::where('redeem_code', $data['redeem_code'])->exists() && $maxTries < 10) {
                    $data['redeem_code'] = rand(100000, 999999);
                    $maxTries++;
                }

                $merchant->update([
                    'redeem_code' => $data['redeem_code'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[MerchantOnboarding] Merchant creation failed: ' . $e->getMessage());
            session()->flash('error', 'Merchant creation failed. Please try again.');
        }

        if (!$merchant) {
            session()->flash('error', 'Merchant creation failed. Please try again.');
            return;
        }

        //save the company logo to the merchant's media collection
        // try {
        //     $company_logo_livewire_tmp = array_values($data['company_logo'])[0];
        //     Log::info($company_logo_livewire_tmp);
        //     Log::info('[MerchantOnboarding] Company logo upload: ' . $company_logo_livewire_tmp->getRealPath());
        //     $merchant->addMediaFromDisk($company_logo_livewire_tmp->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
        //         ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME);
        // } catch (\Exception $e) {
        //     Log::error('[MerchantOnboarding] Company logo upload failed: ' . $e->getMessage());
        //     session()->flash('error', 'Company logo upload failed. Please try again.');
        // }

        //save the company photos to the merchant's media collection
        // try {
        //     foreach ($data['company_photos'] as $company_photo) {
        //         $company_photo_livewire_tmp = $company_photo;
        //         Log::info($company_photo_livewire_tmp);
        //         Log::info('[MerchantOnboarding] Company photo upload: ' . $company_photo_livewire_tmp->getRealPath());
        //         $merchant->addMediaFromDisk($company_photo_livewire_tmp->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
        //             ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);

        //         // also add to store photos
        //         // $store->addMediaFromDisk($company_photo_livewire_tmp->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
        //         //     ->toMediaCollection(Store::MEDIA_COLLECTION_PHOTOS);
        //     }
        // } catch (\Exception $e) {
        //     Log::error('[MerchantOnboarding] Company photos upload failed: ' . $e->getMessage());
        //     session()->flash('error', 'Company photos upload failed. Please try again.');
        // }

        $merchant->save();

        // $businessHours = [];
        // foreach ($data['business_hours'] as $businessHour) {
        //     $businessHours[$businessHour['day']] = [
        //         'open_time' => \Carbon\Carbon::parse($businessHour['open_time'])->format('H:i'),
        //         'close_time' => \Carbon\Carbon::parse($businessHour['close_time'])->format('H:i'),
        //     ];
        // }

        //section for getting lang and long-start
        $lang = null;
        $long = null;

        //get state name and country name
        $state_name = State::find($data['state_id'])->name;
        $country_name = Country::find($data['country_id'])->name;

        $address= $data['address'] . ', ' . $data['zip_code'] . ', ' . $state_name . ', ' . $country_name;
        //dd($address); //"17, jalan usj 18/4, 47630, Selangor, Malaysia"
        $client = new Client();
        $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'query' => [
                'address' => $address,
                'key' => config('filament-google-maps.key'),
            ]
        ]);

        $locationFromGoogle = null;
        if ($response->getStatusCode() === 200) {
            // Parse the response
            $locationFromGoogle = json_decode($response->getBody(), true);

            // Check if the response contains results
            if (isset($locationFromGoogle['results']) && !empty($locationFromGoogle['results'])) {
                $lang = $locationFromGoogle['results'][0]['geometry']['location']['lat'];
                $long = $locationFromGoogle['results'][0]['geometry']['location']['lng'];
            } else {
                // No results found, keep as null first
                $lang = null;
                $long = null;
            }
        } else {
            Log::info('Failed to get location data from Google Maps API');
        }

        // Creeate Store
        // $store = Store::create([
        //     'user_id' => $user->id,
        //     'name' => $data['brand_name'],
        //     'manager_name' => null,
        //     'business_phone_no' => null,
        //     'business_hours' => null,
        //     'address' => $data['address'],
        //     'address_postcode' => $data['zip_code'],
        //     'lang' => $lang,
        //     'long' => $long,
        //     'is_hq' => false,
        //     'state_id' => $data['state_id'],
        //     'country_id' => $data['country_id'],
        // ]);


        // copy from merchant's MEDIA_COLLECTION_NAME_PHOTOS to store's MEDIA_COLLECTION_PHOTOS
        // $merchantPhotos = $merchant->getMedia(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
        // foreach ($merchantPhotos as $photo) {
        //     $photo->copy($store, Store::MEDIA_COLLECTION_PHOTOS);
        // }


        // $locationFromGoogle = $locationFromGoogle['results'][0] ?? null;
        // Log::info('[MerchantRegister] Location data from Google Maps API: ' . json_encode($locationFromGoogle));
        // if ($locationFromGoogle) {
        //     $location = null;
        //     // must create a location data if not exists
        //     if (isset($locationFromGoogle['place_id']) && $locationFromGoogle['place_id'] != 0) {
        //         $location = Location::where('google_id', $locationFromGoogle['place_id'])->first();
        //     } else {
        //         // if location cant be found by google_id, then find by lat,lng
        //         $location = Location::where('lat', $locationFromGoogle['lat'])
        //             ->where('lng', $locationFromGoogle['lng'])
        //             ->first();
        //     }

        //     if (!$location) {
        //         $addressComponents = collect($locationFromGoogle['address_components']);
        //         $city = $addressComponents->filter(function ($component) {
        //             return in_array('locality', $component['types']);
        //         })->first();

        //         $locationData = [
        //             'name' => $data['brand_name'],
        //             'google_id' => isset($locationFromGoogle['place_id']) ? $locationFromGoogle['place_id'] : null,
        //             'lat' => $lang, // google provided
        //             'lng' => $long, // google provided
        //             'address' => $data['address'] ?? '', // user provided
        //             'address_2' => $data['address_2'] ?? '', // user provided
        //             'zip_code' => $data['zip_code'] ?? '', // user provided
        //             'city' => $city['short_name'] ?? '', // google provided
        //             'state_id' => $data['state_id'], // user provided
        //             'country_id' => $data['country_id'], // user provided
        //         ];
        //         Log::info('register', [
        //             'name' => $data['name'],
        //             'google_id' => isset($locationFromGoogle['place_id']) ? $locationFromGoogle['place_id'] : null,
        //             'lat' => $lang, // google provided
        //             'lng' => $long, // google provided
        //             'address' => $data['address'] ?? '', // user provided
        //             'address_2' => $data['address_2'] ?? '', // user provided
        //             'zip_code' => $data['zip_code'] ?? '', // user provided
        //             'city' => $city ?? '', // google provided
        //             'state_id' => $data['state_id'], // user provided
        //             'country_id' => $data['country_id'], // user provided
        //         ]);

        //         // create a new location
        //         $location = Location::create($locationData);

        //         Log::info('[MerchantRegister] Location created: ' . $location->id);
        //     }
        //         // attach store to location
        //     $store->location()->attach($location);
        //     Log::info('[MerchantRegister] Store '. $store->id . 'attached to location: ' . $location->id);
        // } else {
        //     Log::info('[MerchantRegister] Failed to get location data from Google Maps API');
        // }

        if ($user && $merchant) {
            session()->flash('message', 'Your Merchant Account has been created. Please await our admins to approve your account and once approved you will received the instructions to login. Thank you!');
            return redirect()->route('merchant.register');
        }

    }

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Create Account')
                ->schema([
                    Fieldset::make('Phone Number')
                        ->schema([
                            TextInput::make('phone_country_code')
                                ->placeholder('Country Code eg. 60')
                                ->default('60')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state, $livewire) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                })
                                ->columnSpan(['lg' => 1]),
                            TextInput::make('business_phone_no')
                                ->placeholder('eg. 123456789 (without zero infront)')
                                ->label('')
                                ->afterStateHydrated(function ($component, $state, $livewire) {
                                    // ensure no symbols only numbers
                                    $component->state(preg_replace('/[^0-9]/', '', $state));
                                    // remove any number start with zero
                                    $component->state(preg_replace('/^0+/', '', $state));
                                })
                                ->reactive()
                                ->helperText('This phone number will be used to create an account on Funhub. Cannot reuse existing Funhub account registered phone numbers')
                                ->columnSpan(['lg' => 3]),
                        ])->columns(4),
                    TextInput::make('company_email')
                        ->label('Company Email')
                        ->placeholder('Enter Email')
                        ->reactive(),

                    Placeholder::make('password')
                        ->label('Password')
                        ->content(function ($state, Closure $get) {
                            // password will be generated and emailed to you once approved
                            return 'Password will be generated and emailed to the above Company Email once account is approved';
                        })
                    ])->beforeValidation(function ($livewire, $state) {
                        // get data from $livewire->fieldname
                        $data = [
                            'phone_country_code' => $livewire->phone_country_code,
                            'business_phone_no' => $livewire->business_phone_no,
                            'company_email' => $livewire->company_email,
                        ];
                        Validator::make($data, [
                            'phone_country_code' => 'required|max:255',
                            'business_phone_no' => 'required|max:255|unique:users,phone_no',
                            'company_email' => 'required|email|unique:users,email|unique:merchants,email',
                        ])->validate();
                    }),
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
                        Group::make([
                            Section::make('Location Details')
                                ->schema([
                                    //comment out the map coz 'Force https request to maps API issue on production'
                                    // TextInput::make('auto_complete_address')
                                    //     ->label('Find a Location')
                                    //     ->placeholder('Start typing an address ...'),

                                    // Map::make('location')
                                    //     ->autocomplete(
                                    //         fieldName: 'auto_complete_address',
                                    //         placeField: 'name',
                                    //         countries: ['MY'],
                                    //     )
                                    //     ->reactive()
                                    //     ->defaultZoom(15)
                                    //     ->defaultLocation([
                                    //         // klang valley coordinates
                                    //         'lat' => 3.1390,
                                    //         'lng' => 101.6869,
                                    //     ])
                                    //     ->reverseGeocode([
                                    //         'city'   => '%L',
                                    //         'zip'    => '%z',
                                    //         'state'  => '%D',
                                    //         'zip_code' => '%z',
                                    //         'address' => '%n %S',
                                    //     ])
                                    //     ->mapControls([
                                    //         'mapTypeControl'    => true,
                                    //         'scaleControl'      => true,
                                    //         'streetViewControl' => false,
                                    //         'rotateControl'     => true,
                                    //         'fullscreenControl' => true,
                                    //         'searchBoxControl'  => false, // creates geocomplete field inside map
                                    //         'zoomControl'       => false,
                                    //     ])
                                    //     ->clickable(true),

                                    TextInput::make('address')
                                        ->required(),
                                    TextInput::make('zip_code') //merchant's table 'address_postcode'
                                        ->rules('numeric')
                                        ->label('Postcode')
                                        ->required(),
                                    Select::make('state_id') //merchant's table 'state_id'
                                        ->label('State')
                                        ->required()
                                        ->options(State::all()->pluck('name', 'id')->toArray()),
                                    Select::make('country_id') //merchant's table 'country_id'
                                        ->label('Country')
                                        ->default(131)
                                        ->options(Country::all()->pluck('name', 'id')->toArray()),
                                ])
                        ])->columnSpan(['lg' => 1]),
                        // SpatieMediaLibraryFileUpload::make('company_logo')
                        //     ->label('Company Logo')
                        //     ->maxFiles(1)
                        //     ->collection(Merchant::MEDIA_COLLECTION_NAME)
                        //     ->required()
                        //     ->columnSpan('full')
                        //     ->acceptedFileTypes(['image/*'])
                        //     ->rules('image'),
                        // SpatieMediaLibraryFileUpload::make('company_photos')
                        //     ->label('Company Photos')
                        //     ->multiple()
                        //     ->maxFiles(7)
                        //     ->collection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)
                        //     ->required()
                        //     ->columnSpan('full')
                        //     ->acceptedFileTypes(['image/*'])
                        //     ->rules('image'),
                    ])->beforeValidation(function ($livewire, $state) {
                        $data = [
                            'business_name' => $livewire->business_name,
                            'company_reg_no' => $livewire->company_reg_no,
                            'brand_name' => $livewire->brand_name,
                            'address' => $livewire->address,
                            'zip_code' => $livewire->zip_code,
                            'state_id' => $livewire->state_id,
                            'country_id' => $livewire->country_id,
                            // 'company_logo' => $livewire->company_logo,
                            // 'company_photos' => $livewire->company_photos,
                        ];
                        Validator::make($data, [
                            'business_name' => 'required',
                            'company_reg_no' => 'required',
                            'brand_name' => 'required',
                            'address' => 'required',
                            'zip_code' => 'required|numeric',
                            'state_id' => 'required',
                            'country_id' => 'required',
                            // 'company_logo' => 'required',
                            // 'company_photos' => 'required',
                        ])->validate();
                    }),
                Wizard\Step::make('PIC / Authorised Personnel')
                    ->schema([
                        Group::make()
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                Section::make('Person In Charge Information')
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
                                Section::make('Authorised Personnel Information')
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('authorised_personnel_information')
                                            ->label('Authorised Personnel are people who has authority to sign contract on behalf of business'),
                                        TextInput::make('authorised_personnel_designation')
                                            ->required()
                                            ->label('Authorised Personnel Designation'),
                                        TextInput::make('authorised_personnel_name')
                                            ->required()
                                            ->label('Authorised Personnel Name'),
                                        TextInput::make('authorised_personnel_ic_no')
                                            ->required()
                                            ->label('Authorised Personnel IC Number'),
                                    ]),
                        ]),
                    ])->beforeValidation(function ($livewire, $state) {
                        $data = [
                            'pic_name' => $livewire->pic_name,
                            'pic_designation' => $livewire->pic_designation,
                            'pic_ic_no' => $livewire->pic_ic_no,
                            'pic_phone_no' => $livewire->pic_phone_no,
                            'pic_email' => $livewire->pic_email,
                            'authorised_personnel_designation' => $livewire->authorised_personnel_designation,
                            'authorised_personnel_name' => $livewire->authorised_personnel_name,
                            'authorised_personnel_ic_no' => $livewire->authorised_personnel_ic_no,
                        ];
                        Validator::make($data, [
                            // 'pic_ic_no' => 'numeric',
                            'authorised_personne_designation' => 'required',
                            'authorised_personnel_name' => 'required',
                            'authorised_personnel_ic_no' => 'required',
                        ])->validate();
                    }),
                // Wizard\Step::make('Store')
                //     ->schema([
                //         // Repeater::make('stores')
                //         //     ->schema([
                //                 TextInput::make('name') //stores table 'name'
                //                     ->required()
                //                     ->label('Store Name')
                //                     ->columnSpan('full')
                //                     ->placeholder('Enter Store Name'),
                //                 Toggle::make('is_hq')
                //                     ->label('Is Headquarters?')
                //                     ->columnSpan('full'),
                //                 TextInput::make('manager_name') //stores table new column 'manager_name'
                //                     ->label('Manager Name')
                //                     ->required()
                //                     ->placeholder('Enter Manager Name'),
                //                 TextInput::make('store_business_phone_no') //stores table column 'business_phone_no'
                //                     ->label('Store Contact Number')
                //                     ->required()
                //                     ->placeholder('Enter Contact Number for Store'),
                //                 // TextInput::make('address') //stores table 'address'
                //                 // ->label('Store Address')
                //                 // ->required()
                //                 // ->placeholder('Enter Location')
                //                 // ->columnSpan('full'),
                //                 // TextInput::make('address_postcode') //stores table 'address_postcode'
                //                 // ->label('Store Address Postcode')
                //                 // ->required()
                //                 // ->placeholder('Enter Store Address Postcode')
                //                 // ->columnSpan('full'),
                //                 Group::make([
                //                     Section::make('Location Details')
                //                         ->schema([
                //                             //comment out the map coz 'Force https request to maps API issue on production'
                //                             // TextInput::make('auto_complete_address')
                //                             //     ->label('Find a Location')
                //                             //     ->placeholder('Start typing an address ...'),

                //                             // Map::make('location')
                //                             //     ->autocomplete(
                //                             //         fieldName: 'auto_complete_address',
                //                             //         placeField: 'name',
                //                             //         countries: ['MY'],
                //                             //     )
                //                             //     ->reactive()
                //                             //     ->defaultZoom(15)
                //                             //     ->defaultLocation([
                //                             //         // klang valley coordinates
                //                             //         'lat' => 3.1390,
                //                             //         'lng' => 101.6869,
                //                             //     ])
                //                             //     ->reverseGeocode([
                //                             //         'city'   => '%L',
                //                             //         'zip'    => '%z',
                //                             //         'state'  => '%D',
                //                             //         'zip_code' => '%z',
                //                             //         'address' => '%n %S',
                //                             //     ])
                //                             //     ->mapControls([
                //                             //         'mapTypeControl'    => true,
                //                             //         'scaleControl'      => true,
                //                             //         'streetViewControl' => false,
                //                             //         'rotateControl'     => true,
                //                             //         'fullscreenControl' => true,
                //                             //         'searchBoxControl'  => false, // creates geocomplete field inside map
                //                             //         'zoomControl'       => false,
                //                             //     ])
                //                             //     ->clickable(true),

                //                             TextInput::make('address')
                //                                 ->required(),
                //                             TextInput::make('zip_code') //stores table 'address_postcode'
                //                                 ->rules('numeric')
                //                                 ->label('Postcode')
                //                                 ->required(),
                //                             Select::make('state_id') //stores table 'state_id'
                //                                 ->label('State')
                //                                 ->required()
                //                                 ->options(State::all()->pluck('name', 'id')->toArray()),
                //                             Select::make('country_id') //stores table 'country_id'
                //                                 ->label('Country')
                //                                 ->default(131)
                //                                 ->options(Country::all()->pluck('name', 'id')->toArray()),
                //                         ])
                //                 ])->columnSpan('full'),
                //                 Repeater::make('business_hours') //stores table new column 'business_hours'(json)
                //                     ->schema([
                //                         Select::make('day')
                //                             ->options([
                //                                 '1' => 'Monday',
                //                                 '2' => 'Tuesday',
                //                                 '3' => 'Wednesday',
                //                                 '4' => 'Thursday',
                //                                 '5' => 'Friday',
                //                                 '6' => 'Saturday',
                //                                 '7' => 'Sunday',
                //                             ])
                //                             ->required()
                //                             ->label('Day')
                //                             ->columnSpan('full'),
                //                             Grid::make(2)
                //                             ->schema([
                //                                 TimePicker::make('open_time')
                //                                     ->withoutSeconds()
                //                                     ->withoutDate()
                //                                     ->required()
                //                                     ->default('09:00')
                //                                     ->label('Open Time'),
                //                                 TimePicker::make('close_time')
                //                                     ->withoutSeconds()
                //                                     ->withoutDate()
                //                                     ->required()
                //                                     ->default('17:00')
                //                                     ->label('Close Time'),
                //                             ]),
                //                     ])
                //                     ->columns(2)
                //                     ->columnSpan('full'),
                //             // ])
                //             // ->columns(2)
                //     ])->beforeValidation(function ($livewire, $state) {
                //         $data = [
                //             'name' => $livewire->name,
                //             'is_hq' => $livewire->is_hq,
                //             'manager_name' => $livewire->manager_name,
                //             'store_business_phone_no' => $livewire->store_business_phone_no,
                //             'address' => $livewire->address,
                //             'zip_code' => $livewire->zip_code,
                //             'state_id' => $livewire->state_id,
                //             'country_id' => $livewire->country_id,
                //             'business_hours' => $livewire->business_hours,
                //         ];

                //         Validator::make($data, [
                //             'name' => 'required',
                //             'is_hq' => 'boolean',
                //             'manager_name' => 'required',
                //             'store_business_phone_no' => 'required',
                //             'address' => 'required',
                //             'zip_code' => 'required|numeric',
                //             'business_hours' => 'required',
                //             'business_hours.*.day' => 'required',
                //             'business_hours.*.open_time' => 'required',
                //             'business_hours.*.close_time' => 'required',
                //         ])->validate();
                //     }),

            ])
            ->submitAction(new HtmlString('<button type="submit" class="filament-button filament-button-size-sm inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-3 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700" wire:loading.attr="disabled">
                <span wire:loading.remove>Complete Signup</span>
                <span wire:loading>
                    <span class="flex flex-row gap-2">
                    <svg class="animate-spin w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg> Signing Up, Please Wait ...
                    </span>
                </span>
            </button>')),
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

