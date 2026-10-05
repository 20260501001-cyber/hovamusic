<?php

namespace App\Domain\Billing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Polar sipariş olayını (ilk ödeme, yenileme, iade) sipariş geçmişine işler.
 */
class OrderSync
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UnresolvableEvent
     */
    public function apply(array $data): Order
    {
        $providerId = (string) ($data['id'] ?? '');

        if ($providerId === '') {
            throw new UnresolvableEvent('Sipariş kimliği yok.');
        }

        $user = PolarPayload::user($data);

        if ($user !== null) {
            PolarPayload::rememberCustomer($user, $data);
        }

        $order = Order::query()->firstOrNew(['provider_id' => $providerId]);
        $subscriptionId = $data['subscription_id'] ?? null;
        $product = is_array($data['product'] ?? null) ? $data['product'] : [];

        $order->fill([
            'user_id' => $user?->id ?? $order->user_id,
            'subscription_id' => is_string($subscriptionId) ? Subscription::query()->where('provider_id', $subscriptionId)->value('id') : null,
            'plan_id' => Plan::query()->where('polar_product_id', (string) ($data['product_id'] ?? ''))->value('id') ?? $order->plan_id,
            'provider' => 'polar',
            'status' => OrderStatus::tryFrom((string) ($data['status'] ?? '')) ?? ($data['paid'] ?? false ? OrderStatus::Paid : OrderStatus::Pending),
            'billing_reason' => $data['billing_reason'] ?? null,
            'subtotal' => PolarPayload::money($data['subtotal_amount'] ?? $data['amount'] ?? 0),
            'discount' => PolarPayload::money($data['discount_amount'] ?? 0),
            'tax' => PolarPayload::money($data['tax_amount'] ?? 0),
            'total' => PolarPayload::money($data['total_amount'] ?? (($data['net_amount'] ?? $data['amount'] ?? 0) + ($data['tax_amount'] ?? 0))),
            'refunded' => PolarPayload::money($data['refunded_amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? 'usd')),
            'invoice_number' => $data['invoice_number'] ?? $order->invoice_number,
            'product_name' => isset($product['name']) ? mb_substr((string) $product['name'], 0, 200) : $order->product_name,
            'ordered_at' => PolarPayload::date($data['created_at'] ?? null) ?? $order->ordered_at ?? now(),
        ]);
        $order->save();

        return $order;
    }
}
