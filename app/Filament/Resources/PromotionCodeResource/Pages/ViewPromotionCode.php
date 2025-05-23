<?php

namespace App\Filament\Resources\PromotionCodeResource\Pages;

use App\Filament\Resources\PromotionCodeResource;
use Filament\Pages\Actions;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ViewField;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ViewPromotionCode extends ViewRecord
{
    protected static string $resource = PromotionCodeResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewGroup')
                ->label('View Promotion Group')
                ->icon('heroicon-o-collection')
                ->color('secondary')
                ->visible(function () {
                    return $this->record->promotion_code_group_id !== null;
                })
                ->url(function () {
                    // Navigate to the promotion code group view page
                    return route('filament.resources.promotion-code-groups.view', [
                        'record' => $this->record->promotion_code_group_id,
                    ]);
                })
        ];
    }

	protected function getFormSchema(): array
	{
		return [
			TextInput::make('code')
				->label('Promotion Code')
				->disabled(),
                
            Placeholder::make('group_name')
                ->label('Group')
                ->content(function ($record) {
                    if (!$record->promotion_code_group_id) {
                        return 'No Group';
                    }
                    
                    return $record->promotionCodeGroup ? $record->promotionCodeGroup->name : 'Unknown Group';
                }),
		];
	}
}
