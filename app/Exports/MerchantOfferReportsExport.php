<?php

namespace App\Exports;

use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MerchantOfferReportsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {   
        $query = MerchantOfferVoucher::query()
            ->selectRaw('
                merchant_offer_vouchers.merchant_offer_id AS id,
                merchant_offers.name AS offer_name,
                merchant_offers.store_id AS store_id,
                stores.name AS store_name,
                merchant_offers.available_at AS offer_available_at,
                merchant_offers.available_until AS offer_available_until,
                COUNT(merchant_offer_vouchers.id) AS total_purchases,
                merchant_offers.unit_price as funbox_quantity,
                merchant_offers.point_fiat_price as funbox_original,
                merchant_offers.discounted_point_fiat_price as funbox_selling,
                merchant_offers.fiat_price as price_original,
                merchant_offers.discounted_fiat_price as price_selling,
                CASE 
                    WHEN merchant_offers.point_fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_point_fiat_price / merchant_offers.point_fiat_price)) * 100)
                    ELSE 0
                END AS funbox_discount_rate,
                CASE 
                    WHEN merchant_offers.fiat_price > 0 THEN 
                        ((1 - (merchant_offers.discounted_fiat_price / merchant_offers.fiat_price)) * 100)
                    ELSE 0
                END AS discount_rate
            ')
            ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
            ->leftJoin('stores', 'stores.id', '=', 'merchant_offers.store_id')
            ->join('merchant_offer_user', function ($join) {
                $join->on('merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                    ->whereColumn('merchant_offer_user.user_id', '=', 'merchant_offer_vouchers.owned_by_id')
                    ->where('merchant_offer_user.status', '=', MerchantOfferClaim::CLAIM_SUCCESS);
            })
            ->whereNotNull('merchant_offer_vouchers.owned_by_id')
            ->groupBy([
                'merchant_offer_vouchers.merchant_offer_id',
                'merchant_offers.name',
                'merchant_offers.store_id',
                'stores.name',
                'merchant_offers.available_at',
                'merchant_offers.available_until',
                'merchant_offers.unit_price',
                'merchant_offers.point_fiat_price',
                'merchant_offers.discounted_point_fiat_price',
                'merchant_offers.fiat_price',
                'merchant_offers.discounted_fiat_price'
            ])
            ->orderBy('merchant_offers.id', 'ASC')
            ->orderBy('merchant_offers.name', 'DESC')
            ->orderBy('merchant_offers.created_at', 'ASC');

        // Apply date filters if provided
        if ($this->startDate) {
            $query->whereDate('merchant_offer_user.created_at', '>=', Carbon::parse($this->startDate)->format('Y-m-d'));
        }

        if ($this->endDate) {
            $query->whereDate('merchant_offer_user.created_at', '<=', Carbon::parse($this->endDate)->format('Y-m-d'));
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Offer ID',
            'Offer Name',
            'Store Name',
            'Total Purchases Quantity',
            'Funbox Quantity',
            'Funbox Original Price (RM)',
            'Funbox Selling Price (RM)',
            'Funbox Discount Rate',
            'Original Price (RM)',
            'Selling Price (RM)',
            'Discount Rate',
            'Available From',
            'Available Until'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->offer_name,
            $row->store_name,
            $row->total_purchases,
            $row->funbox_quantity,
            number_format($row->funbox_original, 2, '.', ','),
            number_format($row->funbox_selling, 2, '.', ','),
            number_format($row->funbox_discount_rate, 2, '.', ',').' %',
            number_format($row->price_original, 2, '.', ','),
            number_format($row->price_selling, 2, '.', ','),
            number_format($row->discount_rate, 2, '.', ',').' %',
            $row->offer_available_at,
            $row->offer_available_until
        ];
    }

}