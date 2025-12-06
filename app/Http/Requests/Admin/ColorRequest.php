<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 */
class ColorRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->id ?? null;
        
        return [
            'name' => 'required|string|max:255|unique:colors,name,' . $id,
            'code' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', 'unique:colors,code,' . $id],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => translate('the_name_field_is_required'),
            'name.unique' => translate('the_color_name_has_already_been_taken'),
            'code.required' => translate('the_color_code_field_is_required'),
            'code.regex' => translate('the_color_code_must_be_a_valid_hex_color'),
            'code.unique' => translate('the_color_code_has_already_been_taken'),
        ];
    }
}
