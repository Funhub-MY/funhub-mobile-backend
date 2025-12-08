<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use Filament\Pages\Actions;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getActions(): array
    {
        $record = $this->record;
        $actions = [];

        // Add approval actions if campaign requires approval
        if ($record->campaign && $record->campaign->requires_approval) {
            if ($record->approval_status === 'pending') {
                $actions[] = Action::make('approve')
                    ->label('Approve Reservation')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        $this->record->update([
                            'approval_status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'approval_notes' => $data['approval_notes'] ?? null,
                            'status' => 'confirmed',
                        ]);
                        
                        Notification::make()
                            ->title('Reservation Approved')
                            ->success()
                            ->send();
                        
                        $this->redirect(ReservationResource::getUrl('view', ['record' => $this->record]));
                    });

                $actions[] = Action::make('reject')
                    ->label('Reject Reservation')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data): void {
                        $this->record->update([
                            'approval_status' => 'rejected',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                            'status' => 'cancelled',
                        ]);
                        
                        Notification::make()
                            ->title('Reservation Rejected')
                            ->success()
                            ->send();
                        
                        $this->redirect(ReservationResource::getUrl('view', ['record' => $this->record]));
                    });
            }
        }

        return $actions;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add form files to the data for display
        $reservation = Reservation::find($data['id']);
        if ($reservation) {
            $files = $reservation->getFormFiles();
            $fileData = [];
            foreach ($files as $file) {
                $fieldKey = $file->getCustomProperty('field_key');
                $fileData[$fieldKey] = [
                    'url' => $file->getUrl(),
                    'name' => $file->name,
                    'size' => $file->size,
                    'mime_type' => $file->mime_type,
                ];
            }
            $data['form_files'] = $fileData;
        }
        
        return $data;
    }
}
