<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecursiveCloneEpisodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'episode_uuid' => ['required', 'uuid', 'exists:episodes,uuid'],
        ];
    }
}
