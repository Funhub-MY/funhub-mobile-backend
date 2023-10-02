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
     * Get All Faqs
     *
     * @return void
     *
     * @group Help Center
     * @subgroup FAQs
     *
     */
    public function index()
    {
        $faqs = Faq::with('category')
            ->paginate(config('app.paginate_per_page'));

        return FaqResource::collection($faqs);
    }

    public function getFaqCategories()
    {
        $faqCategories = FaqCategory::all();

        return FaqCategoryResource::collection($faqCategories);
    }
}
