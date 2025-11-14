<?php

namespace App\Http\Livewire;

use App\Models\Country;
use App\Models\Merchant;
use App\Models\User;
use App\Models\State;
use App\Models\Store;
use Closure;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Cheesegrits\FilamentGoogleMaps\Fields\Map;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Http\Request;
use Filament\Forms;
use Livewire\Component;
use Filament\Pages\Page;
// use Filament\Forms\Components\Actions;
// use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Resources\Resource;
use Filament\Pages\Dashboard as BasePage;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MerchantDetails extends Page implements HasForms
{
    use InteractsWithForms;
    protected $merchant;
    //public $edited_company_logo = false;

    protected static ?string $model = Merchant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Merchant';

    protected static string $view = 'livewire.merchant-details';
    
    public function mount(): void
    {
        //dd($this);
        //get merchant details
        //$user_id = auth()->user()->id;
        $user_id = 359;
        $this->merchant = Merchant::where('user_id', $user_id)->first();
        //dd($this->merchant);
        $merchant_id = $this->merchant->id;
        $merchant_attributes = $this->merchant->getAttributes();

        //get company logo from media tale 
        $company_logo_url = $this->merchant->getFirstMediaUrl(Merchant::MEDIA_COLLECTION_NAME);
        $company_logo_media = $this->merchant->getFirstMedia(Merchant::MEDIA_COLLECTION_NAME);

        $merchant_attributes['companyLogo'] = [];
        // Set values for the 'companyLogo' key
        $company_logo = [
            'comapany_logo_details' => $company_logo_media
        ];
        $merchant_attributes['companyLogo'] = $company_logo;


        //get and process stores data
        $stores = Store::where('user_id', $user_id)->get();

        //refactor 
        foreach($stores as $store){
            $store->zip_code = $store->address_postcode;

            $store->business_hours = json_decode($store->business_hours, true);

            $businessHours = $store->business_hours;

            // Add "day" key to each entry in the business_hours array
            foreach ($store->business_hours as $day => $hours) {
                $businessHours[$day]['day'] = $day;
            }

            // Update the store's business_hours attribute
            $store->business_hours = $businessHours;
        }

        $this->form->fill(
            [
                'merchant_id' => $merchant_id,
                'business_name' => $merchant_attributes['business_name'],
                'company_reg_no' => $merchant_attributes['company_reg_no'],
                'brand_name' => $merchant_attributes['name'],
                'business_phone_no' => $merchant_attributes['business_phone_no'],
                'address' => $merchant_attributes['address'],
                'zip_code' => $merchant_attributes['address_postcode'],
                'state_id' => $merchant_attributes['state_id'],
                'country_id' => $merchant_attributes['country_id'],
                'email' => $merchant_attributes['email'],
                'companyLogo' => $merchant_attributes['companyLogo'],
                'has_uploaded_new_logo' => false,
                'pic_name' => $merchant_attributes['pic_name'],
                'pic_designation' => $merchant_attributes['pic_designation'],
                'pic_ic_no' => $merchant_attributes['pic_ic_no'],
                'pic_phone_no' => $merchant_attributes['pic_phone_no'],
                'pic_email' => $merchant_attributes['pic_email'],
                'stores' => $stores,

            ] 
        );
        //dd($this->form);
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Tabs')
            ->tabs([
                Tabs\Tab::make('Company Details')
                    ->schema([
                        Group::make([
                            Section::make('Company Details')     
                            ->schema([
                                TextInput::make('merchant_id')
                                ->hidden(),
                                TextInput::make('business_name') 
                                ->label('Company Name (as per SSM)')
                                ->required()
                                // ->getState(fn (Merchant $merchant) => $merchant->business_name = strtoupper($this->merchant->business_name))
                                // ->beforeFormFilled(fn (Merchant $merchant) => $merchant->business_name = strtoupper($this->merchant->business_name))
                                ->columns(1),
                                TextInput::make('company_reg_no') 
                                ->label('Company Registration Number')
                                ->required()
                                ->columns(1),
                                TextInput::make('brand_name') 
                                ->label('Brand Name of Branches')
                                ->required()
                                ->columns(1),
                                TextInput::make('business_phone_no')
                                    ->placeholder('eg. 123456789')
                                    ->label('Business Phone Number')
                                    ->afterStateHydrated(function ($component, $state) {
                                        // ensure no symbols only numbers
                                        $component->state(preg_replace('/[^0-9]/', '', $state));
                                    })
                                    ->rules('required', 'max:255')->columnSpan(['lg' => 1]),
                                
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
                                ])->columnSpan('full'),
                                Toggle::make('has_uploaded_new_logo')
                                ->default(false)
                                ->hidden(),
                                SpatieMediaLibraryFileUpload::make('upload_company_logo')
                                ->label('Company Logo')
                                ->maxFiles(1)
                                ->collection(Merchant::MEDIA_COLLECTION_NAME)
                                ->required()
                                ->afterStateUpdated(function ($state, Merchant $merchant, \Filament\Forms\Get $get) {
                                    //find the merchant
                                    $merchant_id = $get('merchant_id');
                                    $merchant = Merchant::find($merchant_id);
                                    try {
                                        $merchant->addMediaFromDisk($state->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                                        ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME);
                                    } catch (\Exception $e) {
                                        Log::error('[MerchantDetailsEdit] Company logo upload failed: ' . $e->getMessage());
                                    }

                                })
                                ->columnSpan('full')
                                ->acceptedFileTypes(['image/*'])
                                ->rules('image'),
                                Repeater::make('companyLogo')
                                    ->label('')
                                    ->columnSpan('full')
                                    ->extraAttributes([
                                        'class' => 'text-center',
                                    ])
                                    ->schema([
                                        Placeholder::make('company_logo')
                                            ->label('')
                                            ->content(function ($state, \Filament\Forms\Get $get, Merchant $merchant) {
                                                $merchant_id = $get('../../merchant_id');
                                                $merchant = Merchant::find($merchant_id);
                                                $merchant_logos = $merchant->getMedia(Merchant::MEDIA_COLLECTION_NAME);
                                                $latest_logo = $merchant_logos->last();
                                                $media_url = $latest_logo->getFullUrl();
                                                $media_name = $latest_logo->name;
                                                $media_ext = $latest_logo->extension;
                                                // always is file kind of type, not image.
                                                $image = Blade::render('<a href="'.$media_url.'" target="_blank" class="filament-link inline-flex items-center justify-center gap-0.5 font-medium outline-none hover:underline focus:underline text-sm text-primary-600 hover:text-primary-500 filament-tables-link-action"><img src="' . $media_url . '" :label="$name" icon-size="lg" :extension="$extension" /></a>', ['name' => $media_name, 'extension' => $media_ext]);
                                                return new HtmlString($image);
                                            }),
                                        ])
                                    ->disableItemCreation()
                                    // ->hidden(function ($state, Closure $get){
                                    //     return $get('../../has_uploaded_new_logo') ? true : false;
                                    // })
                            ])
                            ->columns(2),
                            ]),
                                Group::make([
                                    Section::make('Login Details')
                                        ->schema([
                                            TextInput::make('email')
                                                ->label('Company Email')
                                                ->email(true)
                                                ->required()
                                                ->placeholder(''),
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
                                ])->columnSpan('full'),
                    ]),
                Tabs\Tab::make('PIC Details')
                    ->schema([
                        Group::make([
                            Section::make('Person In Charge Details')
                                ->schema([
                                    TextInput::make('pic_name')
                                        ->label('PIC Name')
                                        ->required(),
                                    TextInput::make('pic_designation')
                                        ->label('Designation')
                                        ->required(),
                                    TextInput::make('pic_ic_no')
                                        ->label('IC Number')
                                        ->required(),
                                    TextInput::make('pic_phone_no')
                                        ->label('Phone Number')
                                        ->required(),
                                    TextInput::make('pic_email')
                                        ->label('Email')
                                        ->required()
                                        ->rules('required', 'email'),
                                    // Forms\Components\Actions::make([
                                    //     Forms\Components\Actions\Action::make('Save')
                                    //         ->button()
                                    //         ->type('submit')
                                    //         ->primary()
                                    // ])
                                ])
                                // ->actions([
                                //     Action::make('Save')
                                        
                                //         // ->button()
                                //         // ->type('submit')
                                //         // ->primary()
                                // ])
                                ->columns(2),

                        ]),
                    ]),
                    
                Tabs\Tab::make('Store Details')
                    ->schema([
                        Repeater::make('stores')
                        ->schema([
                            TextInput::make('name') //stores table 'name'
                            ->required()
                            ->label('Store Name')
                            ->columns(1)
                            ->placeholder('Enter Store Name'),
                            Toggle::make('is_hq')
                            ->label('Is Headquarters?')
                            ->columns(1),
                            TextInput::make('manager_name') //stores table new column 'manager_name'
                            ->label('Manager Name')
                            ->required()
                            ->placeholder('Enter Manager Name'),
                            TextInput::make('business_phone_no') //stores table column 'business_phone_no'
                            ->label('Contact Number')
                            ->required()
                            ->placeholder('Enter Contact Number'),
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
                                        // TextInput::make('address_postcode') //stores table 'address_postcode'
                                        TextInput::make('zip_code')
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
                            Repeater::make('business_hours')
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
                            ])->columns(2),
                            
                        ])->columns(2)

                    ]),
            ])
        ];
    }

}