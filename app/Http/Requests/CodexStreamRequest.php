<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CodexStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:120000'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'workspace_root' => ['nullable', 'string', 'max:4096'],
            'model' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('codex.models', []))),
            ],
            'reasoning_effort' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('codex.reasoning_efforts', []))),
            ],
            'full_auto' => ['nullable', 'boolean'],
            'cwd' => ['nullable', 'string', 'max:4096'],
            'sandbox_mode' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('codex.sandbox_modes', []))),
            ],
            'approval_policy' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('codex.approval_policies', []))),
            ],
            'web_search' => ['nullable', 'boolean'],
            'add_dirs' => ['nullable', 'array', 'max:8'],
            'add_dirs.*' => ['string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model.in' => 'The selected model is not supported by this Codex Web build.',
            'reasoning_effort.in' => 'The selected reasoning effort is invalid.',
            'sandbox_mode.in' => 'The selected sandbox mode is invalid.',
            'approval_policy.in' => 'The selected approval policy is invalid.',
        ];
    }
}
