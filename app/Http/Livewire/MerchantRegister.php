<?php

namespace App\Http\Livewire;

use App\Models\Merchant;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Http\Request;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\FileUpload;

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
                        TextInput::make('business_name')
                        ->label('Company Name')
                        // ->required()
                        ->placeholder('Enter Company Name'),
                        TextInput::make('registration_no')
                        ->label('Registration Number')
                        // ->required()
                        ->placeholder('Enter Registration Number'),
                        TextInput::make('brand_name')
                        ->label('Brand Name of Branches')
                        // ->required()
                        ->placeholder('Enter Brand Name'),
                        TextInput::make('address')
                        ->label('Company Address')
                        // ->required()
                        ->placeholder('Enter Location'),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('company_logo')
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
                        TextInput::make('name')
                        ->label('PIC Name')
                        ->required()
                        ->placeholder('Enter PIC Name'),
                        TextInput::make('designation')
                        ->label('Designation')
                        ->required()
                        ->placeholder('Enter Designation'),
                        TextInput::make('ic_no')
                        ->label('IC Number')
                        ->required()
                        ->placeholder('Enter IC Number'),
                        TextInput::make('contact_no')
                        ->label('Contact Number')
                        ->required()
                        ->placeholder('Enter Contact Number'),
                        TextInput::make('email')
                        ->label('PIC Email')
                        ->required()
                        ->placeholder('Enter Email'),
                    ]),
                Wizard\Step::make('Store')
                    ->schema([
                        // ...
                    ]),
                Wizard\Step::make('Login')
                    ->schema([
                        // ...
                    ]),
            ])
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

