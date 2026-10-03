<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Multipart mengirim boolean/JSON sebagai string → normalisasi dulu. */
    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    public function rules(): array
    {
        return self::itemRules();
    }

    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}uuid" => ['required', 'uuid'],
            "{$prefix}project_id" => ['required', 'integer'],
            "{$prefix}latitude" => ['required', 'numeric', 'between:-90,90'],
            "{$prefix}longitude" => ['required', 'numeric', 'between:-180,180'],
            "{$prefix}accuracy" => ['nullable', 'numeric', 'min:0'],
            "{$prefix}is_mock" => ['nullable', 'boolean'],
            "{$prefix}note" => ['nullable', 'string', 'max:255'],
            "{$prefix}is_offline" => ['nullable', 'boolean'],
            "{$prefix}abandon_previous" => ['nullable', 'boolean'],
            "{$prefix}captured_at" => ['nullable', 'required_if_accepted:'.$prefix.'is_offline', 'date'],
            "{$prefix}device_time" => ['nullable', 'date'],
            "{$prefix}device" => ['nullable', 'array'],
            "{$prefix}flags" => ['nullable', 'array'],
            "{$prefix}flags.*" => ['string', 'max:50'],
            "{$prefix}photo" => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('presensi.photo.max_kb')],
        ];
    }

    public static function normalize(array $input): array
    {
        foreach (['is_mock', 'is_offline', 'abandon_previous'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $input[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }
        foreach (['device', 'flags'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = json_decode($input[$key], true);
            }
        }

        return $input;
    }
}
