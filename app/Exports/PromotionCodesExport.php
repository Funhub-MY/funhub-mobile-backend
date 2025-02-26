<?php

namespace App\Exports;

use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PromotionCodesExport extends ExcelExport
{
    protected $promotionCodeGroup;

    public function getModelClass(): string
    {
        return PromotionCode::class;
    }

    public function setUp()
    {
        $this
            ->withColumns([
                Column::make('code'),
                Column::make('promotionCodeGroup.name')
                    ->heading('Group Name'),
                Column::make('reward_name')
                    ->heading('Reward')
                    ->getStateUsing(function ($record) {
                        return $record->reward->first() 
                            ? $record->reward->first()->name 
                            : $record->rewardComponent->first()?->name;
                    }),
                Column::make('reward_quantity')
                    ->heading('Quantity')
                    ->getStateUsing(function ($record) {
                        return $record->reward->first() 
                            ? $record->reward->first()->pivot->quantity 
                            : $record->rewardComponent->first()?->pivot?->quantity;
                    }),
                Column::make('is_redeemed')
                    ->heading('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Redeemed' : 'Not Redeemed'),
                Column::make('claimedBy.name')
                    ->heading('Claimed By'),
                Column::make('redeemed_at')
                    ->heading('Redeemed At')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('Y-m-d H:i:s') : ''),
                Column::make('tags')
                    ->heading('Tags')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ''),
            ])
            ->withFilename(fn () => 'promotion-codes-' . date('Y-m-d'))
            ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
            ->queue()
            ->withChunkSize(500);
    }

    public function record($record)
    {
        $this->promotionCodeGroup = $record;
        return $this;
    }

    public function getRows(): array
    {
        // if a specific promotion code group is provided, use that
        if ($this->promotionCodeGroup) {
            return PromotionCode::query()
                ->where('promotion_code_group_id', $this->promotionCodeGroup->id)
                ->with(['reward', 'rewardComponent', 'claimedBy', 'promotionCodeGroup'])
                ->get()
                ->toArray();
        }
        
        // otherwise, use the parent implementation
        return parent::getRows();
    }
}
