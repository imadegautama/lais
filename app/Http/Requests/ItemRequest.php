<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    /**
     * The kind is chosen on create and fixed afterwards.
     */
    public function kind(): string
    {
        $item = $this->route('item');

        return $item instanceof Item ? $item->kind : (string) $this->input('kind');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $aliases = $this->input('aliases');

        if (is_string($aliases)) {
            $this->merge([
                'aliases' => array_values(array_filter(array_map('trim', explode(',', $aliases)))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->currentBusiness()->id;
        $item = $this->route('item');
        $creating = ! $item instanceof Item;
        $kind = $this->kind();

        return [
            'kind' => $creating ? ['required', Rule::in(Item::KINDS)] : ['prohibited'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('items')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($item),
            ],
            'aliases' => ['nullable', 'array', 'max:10'],
            'aliases.*' => ['string', 'max:60'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => $kind === 'bahan' ? ['nullable'] : ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'show_in_store' => ['boolean'],
            'available' => ['boolean'],
            'opening_stock' => $creating && $kind !== 'komposisi' ? ['nullable', 'numeric', 'min:0'] : ['prohibited'],
            'opening_cost' => $creating && $kind !== 'komposisi'
                ? [Rule::requiredIf(fn () => (float) $this->input('opening_stock') > 0), 'nullable', 'integer', 'min:0']
                : ['prohibited'],
            'recipe' => $kind === 'komposisi' ? ['required', 'array', 'min:1'] : ['prohibited'],
            'recipe.*.material_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('items', 'id')
                    ->where('business_id', $businessId)
                    ->whereIn('kind', ['produk', 'bahan'])
                    ->whereNull('deleted_at'),
            ],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.unique' => 'Nama ini sudah dipakai produk lain.',
            'unit.required' => 'Satuan wajib diisi, misalnya porsi atau kg.',
            'price.required' => 'Harga jual wajib diisi.',
            'price.min' => 'Harga jual tidak boleh minus.',
            'opening_cost.required' => 'Isi biaya per unit untuk stok awal.',
            'recipe.required' => 'Tambahkan minimal satu bahan ke resep.',
            'recipe.min' => 'Tambahkan minimal satu bahan ke resep.',
            'recipe.*.material_id.required' => 'Pilih bahannya.',
            'recipe.*.material_id.distinct' => 'Bahan ini sudah ada di resep.',
            'recipe.*.material_id.exists' => 'Bahan tidak ditemukan.',
            'recipe.*.qty.required' => 'Isi takarannya.',
            'recipe.*.qty.gt' => 'Takaran harus lebih dari 0.',
        ];
    }
}
