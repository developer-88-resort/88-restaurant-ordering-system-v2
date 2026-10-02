<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Maya Checkout's REST API (config/services.php → maya).
 *
 * Both calls authenticate with the PUBLIC key (Basic, "pk-...:" base64) —
 * checking a payment's status does not need the secret key. The checkout id
 * Maya returns is also the payment id the status endpoint takes.
 *
 * Maya's own advice is to trust webhooks, not the redirect, for the final
 * status; this app never trusts either blindly — whatever brings the guest
 * or the webhook back, the status is re-read from this API before a bill is
 * finalized.
 */
class MayaCheckoutClient
{
    public static function enabled(): bool
    {
        return (bool) config('services.maya.enabled') && filled(config('services.maya.public_key'));
    }

    /**
     * @param  array<string, mixed>  $payload  Maya's Create Checkout body
     * @return array{checkoutId: string, redirectUrl: string}
     */
    public function createCheckout(array $payload): array
    {
        $response = $this->http()->post('/checkout/v1/checkouts', $payload);

        if ($response->failed() || ! $response->json('checkoutId') || ! $response->json('redirectUrl')) {
            throw new RuntimeException('Maya checkout could not be created: '.$this->describe($response->json(), $response->status()));
        }

        return [
            'checkoutId' => $response->json('checkoutId'),
            'redirectUrl' => $response->json('redirectUrl'),
        ];
    }

    /**
     * Maya's status string for a payment, e.g. PENDING_TOKEN, PENDING_PAYMENT,
     * PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED, PAYMENT_CANCELLED.
     */
    public function paymentStatus(string $paymentId): string
    {
        $response = $this->http()->get('/payments/v1/payments/'.rawurlencode($paymentId).'/status');

        if ($response->failed() || ! $response->json('status')) {
            throw new RuntimeException('Maya payment status could not be read: '.$this->describe($response->json(), $response->status()));
        }

        return (string) $response->json('status');
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.maya.base_url'), '/'))
            ->withBasicAuth((string) config('services.maya.public_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            // Only a dropped connection is retried — an error Maya actually
            // answered with would just come back the same way.
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    protected function describe(mixed $body, int $status): string
    {
        $message = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
        $code = is_array($body) ? ($body['code'] ?? null) : null;

        return trim("HTTP {$status} ".($code ? "[{$code}] " : '').($message ?? ''));
    }
}
