<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Services\FileUploadService;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('order') ? 'msfl_order.edit' : 'msfl_order.add');
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', Rule::exists('msfl_buyers', 'id')],
            'season_id' => ['nullable', Rule::exists('msfl_seasons', 'id')],
            'merchandiser_id' => ['nullable', Rule::exists('users', 'id')],
            'factory_id' => ['nullable', Rule::exists('msfl_factories', 'id')],
            'inquiry_id' => ['nullable', Rule::exists('msfl_inquiries', 'id')],
            'buyer_order_ref' => ['nullable', 'string', 'max:100'],
            'order_date' => ['required', 'date'],
            'currency_id' => ['nullable', Rule::exists('msfl_currencies', 'id')],
            'delivery_term' => ['nullable', Rule::in(array_keys(Order::DELIVERY_TERMS))],
            'payment_term' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', ...FileUploadService::RULES],
        ];
    }
}
