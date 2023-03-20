<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:multimedia,text,video',
            'body' => 'required',
            'status' => 'required|integer|in:0,1',
            'published_at' => 'nullable|date_format:Y-m-d H:i:s',
            'categories' => 'nullable|array|exists:article_categories,id',
            'tags' => 'nullable|array',
            'images' => 'nullable|array',
        ];
    }
}
