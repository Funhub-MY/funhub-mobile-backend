<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
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
            'type' => 'required',
            'id' => 'required',
            'body' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'type.required' => 'Type of Commentable is required',
            'id.required' => 'Id of Commentable is required',
            'body.required' => 'Comment body is required',
        ];
    }
}
