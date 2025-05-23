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
		return [
			'Code', 
			'Group Name', 
			'Code Type', 
			'Total Codes', 
			'Discount Type', 
			'Reward/Amount', 
			'Quantity', 
			'User Type', 
			'Min Spend Amount', 
			'Per User Limit', 
			'Per User Limit Count', 
			'Status', 
			'Claimed By', 
			'Redeemed At', 
			'Tags'
		];
	}

	public function map($row): array
	{
		$promotionCodeGroup = $row->promotionCodeGroup;
		$discountType = optional($promotionCodeGroup)->discount_type ?? '';
		
		// Set reward/amount and quantity based on discount type
		if ($discountType === 'fix_amount') {
			$rewardOrAmount = optional($promotionCodeGroup)->discount_amount ?? '';
			$quantity = '';
		} else {
			$rewardOrAmount = optional($row->reward->first())->name ?? optional($row->rewardComponent->first())->name ?? '';
			$quantity = optional($row->reward->first())->pivot->quantity ?? optional($row->rewardComponent->first())->pivot->quantity ?? '';
		}
		
		// Format discount type for display
		$formattedDiscountType = '';
		if ($discountType === 'fix_amount') {
			$formattedDiscountType = 'Fix Amount';
		} elseif ($discountType === 'reward') {
			$formattedDiscountType = 'Reward';
		}
		
		// Format code type for display
		$codeType = optional($promotionCodeGroup)->code_type ?? '';
		$formattedCodeType = '';
		if ($codeType === 'random') {
			$formattedCodeType = 'Random';
		} elseif ($codeType === 'static') {
			$formattedCodeType = 'Static';
		}
		
		// Format per user limit for display
		$perUserLimit = optional($promotionCodeGroup)->per_user_limit ?? 0;
		$formattedPerUserLimit = '';
		if ($perUserLimit == 0) {
			$formattedPerUserLimit = 'Unlimited';
		} elseif ($perUserLimit == 1) {
			$formattedPerUserLimit = 'Multiple Time';
		}
		
		// Get per user limit count
		$perUserLimitCount = '';
		if ($perUserLimit == 1) {
			$perUserLimitCount = optional($promotionCodeGroup)->per_user_limit_count ?? '';
		}
		
		// Format user type for display
		$userType = optional($promotionCodeGroup)->user_type ?? '';
		$formattedUserType = '';
		if ($userType === 'all') {
			$formattedUserType = 'All Users';
		} elseif ($userType === 'new') {
			$formattedUserType = 'New Users Only';
		} elseif ($userType === 'old') {
			$formattedUserType = 'Old Users Only';
		}
		
		return [
			$row->code,
			optional($promotionCodeGroup)->name ?? '',
			$formattedCodeType,
			optional($promotionCodeGroup)->total_codes ?? '',
			$formattedDiscountType,
			$rewardOrAmount,
			$quantity,
			$formattedUserType,
			optional($promotionCodeGroup)->min_spend_amount ?? '',
			$formattedPerUserLimit,
			$perUserLimitCount,
			$row->is_redeemed ? 'Redeemed' : 'Not Redeemed',
			optional($row->claimedBy)->name ?? '',
			$row->redeemed_at ? $row->redeemed_at->format('Y-m-d H:i:s') : '',
			is_array($row->tags) ? implode(', ', $row->tags) : '',
		];
	}
}
