<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;
use App\Models\PromotionCode;

class PromotionCodesExport implements FromCollection, WithHeadings, WithMapping
{
	protected $promotionCodeGroup;
	protected $promotionCodeIds;

	public function __construct($promotionCodeGroup = null, array $promotionCodeIds = [])
	{
		$this->promotionCodeGroup = $promotionCodeGroup;
		$this->promotionCodeIds = $promotionCodeIds;
	}

	public function collection(): Collection
	{
		$query = PromotionCode::query()->with(['reward', 'rewardComponent', 'claimedBy', 'promotionCodeGroup']);

		if (!empty($this->promotionCodeIds)) {
			return $query->whereIn('id', $this->promotionCodeIds)->get();
		}

		if ($this->promotionCodeGroup) {
			return $query->where('promotion_code_group_id', $this->promotionCodeGroup->id)->get();
		}

		return collect();
	}

	public function headings(): array
	{
		return ['Code', 'Group Name', 'Reward', 'Quantity', 'Status', 'Claimed By', 'Redeemed At', 'Tags'];
	}

	public function map($row): array
	{
		return [
			$row->code,
			optional($row->promotionCodeGroup)->name,
			optional($row->reward->first())->name ?? optional($row->rewardComponent->first())->name,
			optional($row->reward->first())->pivot->quantity ?? optional($row->rewardComponent->first())->pivot->quantity,
			$row->is_redeemed ? 'Redeemed' : 'Not Redeemed',
			optional($row->claimedBy)->name,
			$row->redeemed_at ? $row->redeemed_at->format('Y-m-d H:i:s') : '',
			is_array($row->tags) ? implode(', ', $row->tags) : '',
		];
	}
}
