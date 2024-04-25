<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Resources\MerchantResource;
// use Filament\Resources\Pages\Page;
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
use Filament\Forms\Components\Actions;
// use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Tabs;
use Filament\Resources\Form;
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
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Client;

// class MerchantDetails extends Page
// {
//     protected static ?string $navigationIcon = 'heroicon-o-document-text';

//     protected static string $view = 'filament.pages.merchant-details';
// }
class MerchantDetails extends Page implements HasForms
{
    use InteractsWithForms;
    protected $merchant;
    protected static string $resource = MerchantResource::class;
    protected static ?string $model = Merchant::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Merchant';
    protected static ?string $title = 'Merchant Details';
    protected static ?string $navigationLabel = 'Merchant Details';
    protected static ?string $slug = 'merchant-details';
    // protected static string $view = 'livewire.merchant-details';
    protected static string $view = 'filament.pages.merchant-details';

    protected static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('merchant');
    }

    public function submit(Request $request)
    {
        $user_id = auth()->user()->id;
        try {
            $user = User::findOrFail($user_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            session()->flash('error', 'Error occurred while updating company details. Please try again later.');
            return response()->json(['error' => 'User not found.'], 404);
        }

        //prepare data for update for company details 
        $merchant_id = $this->merchant_id;
        try {
            $merchant = Merchant::findOrFail($merchant_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            session()->flash('error', 'Error occurred while updating company details. Please try again later.');
            return response()->json(['error' => 'Merchant not found.'], 404);
        }

        //data for company details
        $company_details_data = [
            'business_name' => $this->business_name, //name as per ssm
            'company_reg_no' => $this->company_reg_no,
            'name' => $this->brand_name,
            'business_phone_no' => $this->business_phone_no,
            'address' => $this->address,
            'address_postcode' => $this->zip_code,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
        ];

        //check if any field has changed
        $is_company_details_changed = false;
        foreach($company_details_data as $key => $value){
            if($merchant->$key != $value){
                $is_company_details_changed = true;
            } 
        }

        if ($is_company_details_changed) {
            //update company details
            try {
                $merchant->update($company_details_data);
                session()->flash('message', 'Company details updated successfully!');
                return redirect()->route('filament.pages.merchant-details');
            } catch (\Exception $e) {
                Log::error('[MerchantDetailsEdit] Company details update failed: ' . $e->getMessage());
                session()->flash('error', 'Failed to update company details. Please try again later.');
            }
        }

        //data for update for login details
        $merchant_login_email = $this->email;
        $merchant_login_old_password = $this->old_password;
        $merchant_login_new_password = $this->new_password;
        $merchant_login_password_confirmation = $this->password_confirmation;

        //check if user has changed company email
        if($merchant_login_email != $user->email){
            $this->updateCompanyEmail($user, $merchant, $merchant_login_email);
        }

        //check if has new password ->save login details section
        if($merchant_login_new_password != null){
            $this->updateMerchantLoginPassword($user, $merchant_login_old_password, $merchant_login_new_password, $merchant_login_password_confirmation);
        }

        //prepare data for update for pic details
        $pic_details_data = [
            'pic_name' => $this->pic_name,
            'pic_designation' => $this->pic_designation,
            'pic_ic_no' => $this->pic_ic_no,
            'pic_phone_no' => $this->pic_phone_no,
            'pic_email' => $this->pic_email,
        ];

        //check if any field has changed
        $is_pic_details_changed = false;
        foreach($pic_details_data as $key => $value){
            if($merchant->$key != $value){
                $is_pic_details_changed = true;
            } 
        }

        if ($is_pic_details_changed) {
            //update pic details
            try {
                $merchant->update($pic_details_data);
                session()->flash('message', 'Company details updated successfully!');
            } catch (\Exception $e) {
                Log::error('[MerchantDetailsEdit] PIC details update failed: ' . $e->getMessage());
                session()->flash('error', 'Failed to update company details. Please try again later.');
            }
        }

        $data = $this->validate([
            'stores' => 'required',
            'stores.*.name' => 'required',
            'stores.*.is_hq' => 'boolean',
            'stores.*.manager_name' => 'required',
            'stores.*.business_phone_no' => 'required',
            'stores.*.address' => 'required',
            'stores.*.zip_code' => 'required|numeric',
            'stores.*.business_hours' => 'required',
            'stores.*.business_hours.*.day' => 'required',
            'stores.*.business_hours.*.open_time' => 'required',
            'stores.*.business_hours.*.close_time' => 'required',
        ]);

        //prepare data for update for stores details
        $stores_details_data = $this->stores;
        foreach($stores_details_data as $store_to_update){
            //process business hours
            $businessHours = [];
            foreach ($store_to_update['business_hours'] as $businessHour) {
                $businessHours[$businessHour['day']] = [
                    'open_time' => \Carbon\Carbon::parse($businessHour['open_time'])->format('H:i'),
                    'close_time' => \Carbon\Carbon::parse($businessHour['close_time'])->format('H:i'),
                ];
            }
            //update $store_to_update['business_hours']
            $store_to_update['business_hours'] = $businessHours;
            //if store has id, then update, else create new store
            if(array_key_exists('id', $store_to_update) && $store_to_update['id'] !== null){
                //update store
                try {
                    $store_id = $store_to_update['id'];
                    $store = Store::findOrFail($store_id);
                    $store->update($store_to_update);
                } catch (\Exception $e) {
                    Log::error('[MerchantDetailsEdit] Store details update failed: ' . $e->getMessage());
                    session()->flash('error', 'Failed to update store details. Please try again later.');
                }
            } else {
                // if ($store_to_update['location'] === null) {
                //     session()->flash('error', 'Please pin your location for the store.');
                //     return;
                // }

                //section for getting lang and long-start
                //get state name and country name
                $state_name = State::find($store_to_update['state_id'])->name;
                $country_name = Country::find($store_to_update['country_id'])->name;

                $address= $store_to_update['address'] . ', ' . $store_to_update['zip_code'] . ', ' . $state_name . ', ' . $country_name;
                //dd($address); //"17, jalan usj 18/4, 47630, Selangor, Malaysia"
                $client = new Client();
                $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'query' => [
                        'address' => $address,
                        'key' => config('filament-google-maps.key'),
                    ]
                ]);
            
                // Parse the response
                $location_data = json_decode($response->getBody(), true);
                $lang = $location_data['results'][0]['geometry']['location']['lat'];
                $long = $location_data['results'][0]['geometry']['location']['lng'];
                //section for getting lang and long-end

                //create new store
                try {
                    $store = Store::create([
                        'user_id' => $user->id,
                        'name' => $store_to_update['name'],
                        'manager_name' => $store_to_update['manager_name'],
                        'business_phone_no' => $store_to_update['business_phone_no'],
                        'business_hours' => json_encode($businessHours),
                        'address' => $store_to_update['address'],
                        'address_postcode' => $store_to_update['zip_code'],
                        'lang' => $lang,
                        'long' => $long,
                        'is_hq' => $store_to_update['is_hq'],
                        'state_id' => $store_to_update['state_id'],
                        'country_id' => $store_to_update['country_id'],
                    ]);

                    Log::info('[MerchantDetailsEdit] Store details created: ' . $store);

                    session()->flash('message', 'Company details updated successfully!');
                } catch (\Exception $e) {
                    Log::error('[MerchantDetailsEdit] Store details create failed: ' . $e->getMessage());
                    session()->flash('error', 'Failed to create store details. Please try again later.');
                }
            }

        }


    }
    
    public function updateCompanyEmail(User $user, Merchant $merchant, $merchant_login_email)
    {
        //check if email already exists in the User table
        $is_user_exists = User::where('email', $merchant_login_email)->exists();
        if($is_user_exists){
            session()->flash('error', 'Email already exists.');
            return redirect()->route('filament.pages.merchant-details');
        }
        //update email
        try {
            $user->email = $merchant_login_email;
            $user->save();
        } catch (\Exception $e) {
            Log::error('[MerchantDetailsEdit] Company email update failed: ' . $e->getMessage());
        }
        try{
            $merchant->email = $merchant_login_email;
            $merchant->save();
        } catch (\Exception $e) {
            Log::error('[MerchantDetailsEdit] Company email update failed: ' . $e->getMessage());
        }

        session()->flash('message', 'Company details updated successfully.');
        return redirect()->route('filament.pages.merchant-details');
    }

    public function updateMerchantLoginPassword(User $user, $merchant_login_old_password, $merchant_login_new_password, $merchant_login_password_confirmation){
            //check if old password is correct
            $user_password = $user->password;
            if (Hash::check($merchant_login_old_password, $user_password)) {
                // The passwords match...
                //check if new password and confirm password is the same
                if($merchant_login_new_password != $merchant_login_password_confirmation){
                    session()->flash('error', 'New password and confirm password is not the same.');
                    return redirect()->route('filament.pages.merchant-details');
                }
                //check if new password is the same as old password
                if($merchant_login_new_password == $merchant_login_old_password){
                    session()->flash('error', 'New password cannot be the same as old password.');
                    return redirect()->route('filament.pages.merchant-details');
                }
                //check if new password is at least 8 characters
                if(strlen($merchant_login_new_password) < 8){
                    session()->flash('error', 'New password must be at least 8 characters.');
                    return redirect()->route('filament.pages.merchant-details');
                }
                //check if new password is alphanumeric
                if(!ctype_alnum($merchant_login_new_password)){
                    session()->flash('error', 'New password must be alphanumeric.');
                    return redirect()->route('filament.pages.merchant-details');
                }

                //proceed to update password
                $user->password = Hash::make($merchant_login_new_password);
                $user->save();
                session()->flash('message', 'Company details updated successfully.');
                return redirect()->route('filament.pages.merchant-details');
            } else {
                session()->flash('error', 'Old password is incorrect.');
                return redirect()->route('filament.pages.merchant-details');
            }
    }
    
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('merchant'), 403);
        
        $user_id = auth()->user()->id;
        //$user_id = 359;
        //$user = User::find($user_id);

        //get merchant details
        $this->merchant = Merchant::where('user_id', $user_id)->first();
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

        //get company photos from media table
        $company_photos = $this->merchant->getMedia(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
        $merchant_attributes['companyPhotos'] = [];
        $merchant_attributes['companyPhotos'][0] = $company_photos;

        //get and process stores data
        $stores = Store::where('user_id', $user_id)->get();

        //dd($stores);

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
                'companyPhotos' => $merchant_attributes['companyPhotos'],
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
                                ->afterStateUpdated(function ($state, Merchant $merchant, Closure $get) {
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
                                            ->content(function ($state, Closure $get, Merchant $merchant) {
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
                                    ->disableItemCreation(),
                                    // ->hidden(function ($state, Closure $get){
                                    //     return $get('../../has_uploaded_new_logo') ? true : false;
                                    // })

                                //add/edit merchant photos start 
                                SpatieMediaLibraryFileUpload::make('upload_company_photos')
                                    ->label('Company Photos')
                                    // ->multiple()
                                    ->maxFiles(7)
                                    ->collection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS)
                                    ->required()
                                    ->afterStateUpdated(function ($state, Merchant $merchant, Closure $get) {
                                        //find the merchant
                                        $merchant_id = $get('merchant_id');
                                        $merchant = Merchant::find($merchant_id);

                                        try {
                                            // foreach ($state as $file) {
                                            //     $merchant->addMediaFromDisk($file->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                                            //         ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
                                            // }
                                            
                                            $merchant->addMediaFromDisk($state->getRealPath(), (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')))
                                            ->toMediaCollection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
                                        } catch (\Exception $e) {
                                            Log::error('[MerchantDetailsEdit] Company logo upload failed: ' . $e->getMessage());
                                        }
    
                                    })
                                    ->columnSpan('full')
                                    ->acceptedFileTypes(['image/*'])
                                    ->rules('image'),
                                Repeater::make('companyPhotos')
                                    ->label('')
                                    ->columnSpan('full')
                                    ->extraAttributes([
                                        'class' => 'text-center',
                                    ])
                                    ->schema([
                                        Placeholder::make('company_photo')
                                            ->label('')
                                            ->content(function ($state, Closure $get, Merchant $merchant) {
                                                $merchant_id = $get('../../merchant_id');
                                                $merchant = Merchant::find($merchant_id);
                                                $merchant_photos = $merchant->getMedia(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
                                                $images = '';
                                                foreach($merchant_photos as $merchant_photo){
                                                    $media_url = $merchant_photo->getFullUrl();
                                                    $media_name = $merchant_photo->name;
                                                    $media_ext = $merchant_photo->extension;
                                                    //$images .= Blade::render('<a href="'.$media_url.'" target="_blank" class="filament-link inline-flex items-center justify-center gap-0.5 font-medium outline-none hover:underline focus:underline text-sm text-primary-600 hover:text-primary-500 filament-tables-link-action"><img src="' . $media_url . '" :label="$name" icon-size="lg" :extension="$extension" /></a>', ['name' => $media_name, 'extension' => $media_ext]);
                                                    $images .= '<div class="mb-4 py-4 border">' . Blade::render('<a href="'.$media_url.'" target="_blank" class="filament-link inline-flex items-center justify-center gap-0.5 font-medium outline-none hover:underline focus:underline text-sm text-primary-600 hover:text-primary-500 filament-tables-link-action"><img src="' . $media_url . '" :label="$name" icon-size="lg" :extension="$extension" /></a>', ['name' => $media_name, 'extension' => $media_ext]) . '</div>';
                                                }
                                                return new HtmlString($images);
                                            }),
                                        ])
                                    ->disableItemCreation()
                                    // ->afterStateHydrated(function ($state, Merchant $merchant, Closure $get) {
                                    //     //find the merchant
                                    //     $merchant_id = $get('merchant_id');
                                    //     $merchant = Merchant::find($merchant_id);
                                    //     //delete all old photos
                                    //     $merchant->clearMediaCollection(Merchant::MEDIA_COLLECTION_NAME_PHOTOS);
                                    // }),
                                    
                                //add/edit merchant photos end 
                                    ]),
                            ViewField::make('company_details_save_button')
                            ->view('livewire.save-button')
                            ->columns(2),
                            ]),
                                Group::make([
                                    Section::make('Login Details')
                                        ->schema([
                                            TextInput::make('email') // this field linked to users table 'email' as this is for login
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
                                            ViewField::make('login_save_button')
                                                ->view('livewire.save-button')
                                        ]),
                                ])
                                ->columnSpan('full'),

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
                                    ViewField::make('pic_save_button')
                                    ->view('livewire.save-button')
                                    ->columnSpanFull(),
                                    // Actions::make([
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
                            TextInput::make('name') 
                            ->required()
                            ->label('Store Name')
                            ->columns(1)
                            ->placeholder('Enter Store Name'),
                            Toggle::make('is_hq')
                            ->label('Is Headquarters?')
                            ->columns(1),
                            TextInput::make('manager_name') 
                            ->label('Manager Name')
                            ->required()
                            ->placeholder('Enter Manager Name'),
                            TextInput::make('business_phone_no') 
                            ->label('Contact Number')
                            ->required()
                            ->placeholder('Enter Contact Number'),
                            Group::make([
                                Section::make('Location Details')
                                    ->schema([
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
                            
                        ])->columns(2),
                        ViewField::make('pic_save_button')
                        ->view('livewire.save-button')
                        ->columnSpanFull(),
                    ]),
            ])
        ];
    }

}

