<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineItems = is_array($this['line_items'] ?? null) ? $this['line_items'] : [];

        return [
            'invoice_id' => $this['invoice_id'] ?? null,
            'invoice_number' => $this['invoice_number'] ?? null,
            'date' => $this['date'] ?? null,
            'due_date' => $this['due_date'] ?? null,
            'status' => $this['status'] ?? null,
            'payment_status' => $this['payment_status'] ?? ($this['status'] ?? null),
            'currency_code' => $this['currency_code'] ?? null,
            'customer_name' => $this['customer_name'] ?? null,
            'customer_id' => $this['customer_id'] ?? null,
            'subscription_id' => $this['subscription_id'] ?? null,
            'billing_address' => [
                'attention' => data_get($this->resource, 'billing_address.attention'),
                'address' => data_get($this->resource, 'billing_address.address'),
                'street2' => data_get($this->resource, 'billing_address.street2'),
                'city' => data_get($this->resource, 'billing_address.city'),
                'state' => data_get($this->resource, 'billing_address.state'),
                'zip' => data_get($this->resource, 'billing_address.zip'),
                'country' => data_get($this->resource, 'billing_address.country'),
            ],
            'line_items' => collect($lineItems)->map(fn ($item) => [
                'line_item_id' => $item['line_item_id'] ?? null,
                'name' => $item['name'] ?? null,
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'rate' => $item['rate'] ?? null,
                'item_total' => $item['item_total'] ?? null,
                'tax_id' => $item['tax_id'] ?? null,
                'tax_name' => $item['tax_name'] ?? null,
                'tax_amount' => $item['tax_amount'] ?? null,
            ])->values()->all(),
            'subtotal' => $this['sub_total'] ?? ($this['subtotal'] ?? null),
            'tax' => $this['tax_total'] ?? ($this['tax'] ?? null),
            'total' => $this['total'] ?? null,
            'balance' => $this['balance'] ?? null,
            'notes' => $this['notes'] ?? null,
            'terms' => $this['terms'] ?? null,
            'invoice_url' => $this['invoice_url'] ?? null,
            'pdf_url' => $this['pdf_url'] ?? null,
            'download_pdf_url' => isset($this['invoice_id']) ? '/api/v1/billing/invoices/' . $this['invoice_id'] . '/pdf' : null,
            'created_time' => $this['created_time'] ?? null,
            'last_payment_date' => $this['last_payment_date'] ?? null,
        ];
    }
}
