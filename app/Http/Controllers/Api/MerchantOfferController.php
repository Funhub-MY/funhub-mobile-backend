<?php

namespace App\Http\Controllers;

use App\Http\Resources\MerchantOfferResource;
use App\Models\MerchantOffer;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;

class MerchantOfferController extends Controller
{
    use QueryBuilderTrait;

    public function index(Request $request)
    {
        $query = MerchantOffer::query()->with('merchant', 'merchant.user');

        // category_ids filter
        if ($request->has('category_ids')) {
            $query->whereHas('categories', function ($query) use ($request) {
                $query->whereIn('id', $request->category_ids);
            });
        }

        $this->buildQuery($query, $request);

        $data = $query->paginate(config('app.paginate_per_page'));

        return MerchantOfferResource::collection($data);
    }

    public function show(MerchantOffer $merchantOffer)
    {
        return new MerchantOfferResource($merchantOffer);
    }

    public function postClaimOffer()
    {

    }
}
