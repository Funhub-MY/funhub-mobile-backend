<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleImagesUploadRequest extends FormRequest
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
            'images' => $this
                ->validateMultipleMedia()
                ->minItems(1)
                ->maxItems(config('app.max_images_per_article'))
                ->extension('png', 'jpg', 'jpeg', 'heic', 'heif')
                ->maxItemSizeInKb(config('app.max_size_per_image_kb'))
        ];
    }
}
