<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Get Countries
     *
     * @return JsonResponse
     *
     * @group Other
     * @subgroup Country
     * @response scenario="success" {
     * ["id" => 1, "name" => "Nigeria", "code" => "NG"],
     * }
     */
    public function getCountries()
    {
        $countries = Country::orderBy('name', 'ASC')
            ->get();

        return response()->json(CountryResource::collection($countries));
    }
}
