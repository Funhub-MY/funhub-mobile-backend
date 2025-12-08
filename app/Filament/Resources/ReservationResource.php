<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Filament\Resources\ReservationResource\RelationManagers;
use App\Models\Reservation;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\BadgeService;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Events';

    protected static ?int $navigationSort = 1;

    protected static function getNavigationBadge(): ?string
    {
        $pendingCount = Reservation::where('approval_status', 'pending')
            ->whereHas('campaign', function ($query) {
                $query->where('requires_approval', true);
            })
            ->count();
        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function canCreate(): bool
    {
        return false; // Reservations are created via API
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Reservation Details')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->disabled()
                            ->required(),
                        
                        Select::make('campaign_id')
                            ->label('Campaign')
                            ->relationship('campaign', 'title')
                            ->searchable()
                            ->disabled()
                            ->required(),
                        
                        DateTimePicker::make('reservation_date')
                            ->label('Reservation Date')
                            ->disabled()
                            ->required(),
                    ])
                    ->columns(2),
                
                Section::make('Approval Information')
                    ->schema([
                        Select::make('approval_status')
                            ->label('Approval Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->campaign && $record->campaign->requires_approval),
                        
                        Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approvedBy', 'name')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->approved_by),
                        
                        TextInput::make('approved_at')
                            ->label('Approved At')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? $state->format('d/m/Y H:i') : null)
                            ->visible(fn ($record) => $record && $record->approved_at),
                        
                        Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(3)
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->approval_notes),
                        
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(3)
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->rejection_reason),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record && $record->campaign && $record->campaign->requires_approval),
                
                Section::make('Form Submission Data')
                    ->description('All form data submitted by the user for admin verification')
                    ->schema([
                        Placeholder::make('form_data_display')
                            ->label('Form Data')
                            ->content(function ($record) {
                                if (!$record || !$record->form_data) return 'No form data available.';
                                
                                // Get campaign form fields to map field_key to labels
                                $formFieldConfig = \App\Models\ReservationFormField::where('campaign_id', $record->campaign_id)
                                    ->where('is_active', true)
                                    ->first();
                                
                                $formFields = $formFieldConfig ? collect($formFieldConfig->form_fields) : collect();
                                $fieldLabels = $formFields->pluck('label', 'field_key')->toArray();
                                
                                $formData = $record->form_data;
                                $html = '<div class="space-y-4">';
                                
                                foreach ($formData as $fieldKey => $value) {
                                    // Skip file fields (they're shown separately)
                                    if (is_array($value) && isset($value['media_id'])) {
                                        continue;
                                    }
                                    
                                    $label = $fieldLabels[$fieldKey] ?? $fieldKey;
                                    
                                    // Format value based on type
                                    $displayValue = $value;
                                    if (is_array($value)) {
                                        $displayValue = implode(', ', $value);
                                    } elseif (is_bool($value)) {
                                        $displayValue = $value ? 'Yes' : 'No';
                                    } elseif ($value === null || $value === '') {
                                        $displayValue = '<span class="text-gray-400">Not provided</span>';
                                    }
                                    
                                    $html .= "<div class='border-b pb-3'>";
                                    $html .= "<p class='font-semibold text-sm text-gray-700 mb-1'>{$label}</p>";
                                    $html .= "<p class='text-sm text-gray-900'>{$displayValue}</p>";
                                    $html .= "</div>";
                                }
                                
                                $html .= '</div>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->form_data),
                
                Section::make('Attached Files & Images')
                    ->description('Uploaded files and images for verification')
                    ->schema([
                        Placeholder::make('form_files_display')
                            ->label('Files')
                            ->content(function ($record) {
                                if (!$record) return null;
                                
                                $files = $record->getFormFiles();
                                if ($files->isEmpty()) return '<p class="text-gray-500">No files uploaded.</p>';
                                
                                // Get campaign form fields to map field_key to labels
                                $formFieldConfig = \App\Models\ReservationFormField::where('campaign_id', $record->campaign_id)
                                    ->where('is_active', true)
                                    ->first();
                                
                                $formFields = $formFieldConfig ? collect($formFieldConfig->form_fields) : collect();
                                $fieldLabels = $formFields->pluck('label', 'field_key')->toArray();
                                
                                $html = '<div class="space-y-6">';
                                
                                // Group files by field_key
                                $filesByField = [];
                                foreach ($files as $file) {
                                    $fieldKey = $file->getCustomProperty('field_key') ?? 'unknown';
                                    if (!isset($filesByField[$fieldKey])) {
                                        $filesByField[$fieldKey] = [];
                                    }
                                    $filesByField[$fieldKey][] = $file;
                                }
                                
                                foreach ($filesByField as $fieldKey => $fileGroup) {
                                    $label = $fieldLabels[$fieldKey] ?? $fieldKey;
                                    $html .= "<div class='border rounded-lg p-4 bg-gray-50'>";
                                    $html .= "<h4 class='font-semibold text-sm text-gray-800 mb-3'>{$label}</h4>";
                                    $html .= "<div class='grid grid-cols-1 md:grid-cols-3 gap-4'>";
                                    
                                    foreach ($fileGroup as $file) {
                                        $url = $file->getUrl();
                                        $name = $file->name;
                                        $size = number_format($file->size / 1024, 2);
                                        $mimeType = $file->mime_type;
                                        $isImage = strpos($mimeType, 'image/') === 0;
                                        
                                        $html .= "<div class='border rounded p-3 bg-white'>";
                                        
                                        if ($isImage) {
                                            // Show image preview
                                            $html .= "<a href='{$url}' target='_blank' class='block mb-2'>";
                                            $html .= "<img src='{$url}' alt='{$name}' class='w-full h-32 object-cover rounded border' />";
                                            $html .= "</a>";
                                        } else {
                                            // Show file icon
                                            $html .= "<div class='w-full h-32 bg-gray-100 rounded border flex items-center justify-center mb-2'>";
                                            $html .= "<svg class='w-12 h-12 text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>";
                                            $html .= "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' />";
                                            $html .= "</svg>";
                                            $html .= "</div>";
                                        }
                                        
                                        $html .= "<p class='text-xs font-medium text-gray-700 truncate' title='{$name}'>{$name}</p>";
                                        $html .= "<p class='text-xs text-gray-500'>{$size} KB</p>";
                                        $html .= "<a href='{$url}' target='_blank' class='text-xs text-blue-600 hover:underline mt-1 inline-block'>View Full Size</a>";
                                        $html .= "</div>";
                                    }
                                    
                                    $html .= "</div>";
                                    $html .= "</div>";
                                }
                                
                                $html .= '</div>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->getFormFiles()->isNotEmpty()),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['campaign', 'user', 'approvedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                TextColumn::make('reservation_date')
                    ->label('Reservation Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                BadgeColumn::make('approval_status')
                    ->label('Approval')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->sortable()
                    ->visible(fn () => Reservation::whereHas('campaign', function ($query) {
                        $query->where('requires_approval', true);
                    })->exists()),
                
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->sortable()
                    ->visible(fn () => Reservation::whereNotNull('approved_by')->exists()),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'title')
                    ->searchable(),
                
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                
                SelectFilter::make('approval_status')
                    ->label('Approval Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->visible(fn () => Reservation::whereHas('campaign', function ($query) {
                        $query->where('requires_approval', true);
                    })->exists()),
                
                SelectFilter::make('requires_approval')
                    ->label('Requires Approval')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'yes') {
                            return $query->whereHas('campaign', function ($q) {
                                $q->where('requires_approval', true);
                            });
                        } elseif ($data['value'] === 'no') {
                            return $query->whereHas('campaign', function ($q) {
                                $q->where('requires_approval', false);
                            });
                        }
                        return $query;
                    })
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(3),
                    ])
                    ->action(function (Reservation $record, array $data): void {
                        if ($record->approval_status === 'approved') {
                            Notification::make()
                                ->title('Already Approved')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        if ($record->approval_status === 'rejected') {
                            Notification::make()
                                ->title('Cannot approve a rejected reservation')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        $record->update([
                            'approval_status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'approval_notes' => $data['approval_notes'] ?? null,
                            'status' => 'confirmed', // Auto-confirm when approved
                        ]);
                        
                        // Award badge to user
                        $badgeService = new BadgeService();
                        $userBadge = $badgeService->awardBadgeForReservation($record);
                        
                        $message = 'Reservation Approved';
                        if ($userBadge) {
                            $message .= ' - Badge "' . $userBadge->badge->name . '" awarded!';
                        }
                        
                        Notification::make()
                            ->title($message)
                            ->success()
                            ->send();
                    })
                    ->visible(function (Reservation $record): bool {
                        // Ensure campaign is loaded
                        if (!$record->relationLoaded('campaign')) {
                            $record->load('campaign');
                        }
                        
                        return $record->campaign 
                            && $record->campaign->requires_approval 
                            && $record->approval_status === 'pending';
                    }),
                
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Reservation $record, array $data): void {
                        if ($record->approval_status === 'rejected') {
                            Notification::make()
                                ->title('Already Rejected')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        if ($record->approval_status === 'approved') {
                            Notification::make()
                                ->title('Cannot reject an approved reservation')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        $record->update([
                            'approval_status' => 'rejected',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                            'status' => 'cancelled', // Auto-cancel when rejected
                        ]);
                        
                        Notification::make()
                            ->title('Reservation Rejected')
                            ->success()
                            ->send();
                    })
                    ->visible(function (Reservation $record): bool {
                        // Ensure campaign is loaded
                        if (!$record->relationLoaded('campaign')) {
                            $record->load('campaign');
                        }
                        
                        return $record->campaign 
                            && $record->campaign->requires_approval 
                            && $record->approval_status === 'pending';
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
    
    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'view' => Pages\ViewReservation::route('/{record}'),
        ];
    }    
}
