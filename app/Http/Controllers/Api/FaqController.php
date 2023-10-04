<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get All FAQs
     *
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup FAQs
     * @bodyParam category_ids array optional Array of category ids. Example: [1,2,3]
     * @bodyParam query string optional Search query. Example: How to ...
     *
     * @response scenario="success" {
     * "data": []
     * }
     *
     */
    public function index(Request $request)
    {
        $query = Faq::with('category')->published();

        if ($request->has('category_ids')) {
            $query->whereIn('category_id', $request->category_ids);
        }

        if ($request->has('query')) {
            $query->where('question', 'like', '%' . $request->query . '%');
        }

        $faqs = $query->paginate(config('app.paginate_per_page'));

        return FaqResource::collection($faqs);
    }

    /**
     * Get All FAQs Categories
     *
     * @return JsonResponse
     *
     * @group Help Center
     * @subgroup FAQs
     * @response scenario="success" {
     * "data": []
     * }
     */
    public function getFaqCategories()
    {
        $faqCategories = FaqCategory::all();

        return FaqCategoryResource::collection($faqCategories);
    }
}
