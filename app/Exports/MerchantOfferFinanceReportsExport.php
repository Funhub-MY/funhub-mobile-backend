<?php

namespace App\Exports;

use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MerchantOfferFinanceReportsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;
    protected $purchaseMethod;

    public function __construct($startDate = null, $endDate = null, $purchaseMethod = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->purchaseMethod = $purchaseMethod;
    }

    public function query()
    {   
        $query = MerchantOfferVoucher::query()
            ->selectRaw('
                merchant_offer_vouchers.id AS id,
                merchant_offer_vouchers.code AS code,
                merchant_offers.name AS offer_name,
                merchant_offer_user.purchase_method AS purchase_method,
                merchant_offer_user.total AS amount_total,
                merchant_offer_user.created_at AS transaction_date,
                transactions.transaction_no AS transaction_no,
                transactions.amount AS amount,
                transactions.gateway_transaction_id AS reference_no,
                transactions.payment_method AS payment_method,
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
            ->join('merchant_offer_user', function ($join) {
                $join->on('merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                    ->whereColumn('merchant_offer_user.user_id', '=', 'merchant_offer_vouchers.owned_by_id')
                    ->where('merchant_offer_user.status', '=', MerchantOfferClaim::CLAIM_SUCCESS);
            })
            ->leftJoin('transactions', 'transactions.transaction_no', '=', 'merchant_offer_user.transaction_no')
            ->whereNotNull('merchant_offer_vouchers.owned_by_id')
            ->groupBy([
                'merchant_offer_vouchers.id',
                'merchant_offer_vouchers.code',
                'merchant_offers.name',
                'merchant_offer_user.purchase_method',
                'merchant_offer_user.total',
                'merchant_offer_user.created_at',
                'transactions.transaction_no',
                'transactions.amount',
                'transactions.gateway_transaction_id',
                'transactions.payment_method',
                'merchant_offers.unit_price',
                'merchant_offers.point_fiat_price',
                'merchant_offers.discounted_point_fiat_price',
                'merchant_offers.fiat_price',
                'merchant_offers.discounted_fiat_price'
            ])
            ->orderBy('merchant_offer_user.created_at', 'DESC')
            ->orderBy('merchant_offers.name', 'DESC');

        // Apply date filters if provided
        if ($this->startDate) {
            $query->whereDate('merchant_offer_user.created_at', '>=', Carbon::parse($this->startDate)->format('Y-m-d'));
        }

        if ($this->endDate) {
            $query->whereDate('merchant_offer_user.created_at', '<=', Carbon::parse($this->endDate)->format('Y-m-d'));
        }

        if ($this->purchaseMethod) {
            $query->whereDate('merchant_offer_user.purchase_method', '=', $purchaseMethod);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Offer Name',
            'Voucher Code',
            'Transaction Date',
            'Purchase Method',
            'Purchase Amount',
            'Transaction No (MPAY)',
            'Purchase Amount (MPAY)',
            'Reference No (MPAY)',
            'Payment Method (MPAY)',
            'Funbox Original Price (RM)',
            'Funbox Selling Price (RM)',
            'Funbox Discount Rate',
            'Original Price (RM)',
            'Selling Price (RM)',
            'Discount Rate'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->offer_name,
            $row->code,
            $row->transaction_date,
            $row->purchase_method === 'fiat' ? 'Cash' : ($row->purchase_method === 'points' ? 'Funbox' : 'Unknown'),
            $row->amount_total,
            $row->transaction_no,
            $row->amount,
            $row->reference_no,
            $row->payment_method,
            number_format($row->funbox_original, 2, '.', ','),
            number_format($row->funbox_selling, 2, '.', ','),
            number_format($row->funbox_discount_rate, 2, '.', ',').' %',
            number_format($row->price_original, 2, '.', ','),
            number_format($row->price_selling, 2, '.', ','),
            number_format($row->discount_rate, 2, '.', ',').' %',
        ];
    }

}