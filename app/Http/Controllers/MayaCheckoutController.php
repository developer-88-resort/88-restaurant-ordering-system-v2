<?php

namespace App\Http\Controllers;

use App\Enums\OnlinePaymentStatus;
use App\Http\Requests\StartMayaCheckoutRequest;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Services\Payments\MayaCheckoutClient;
use App\Services\Payments\MayaCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Order Management's "Maya Checkout (online)" payment: hands the bill to
 * Maya's hosted checkout page and finalizes it once Maya confirms the money.
 * See MayaCheckoutService for the rules.
 */
class MayaCheckoutController extends Controller
{
    public function __construct(protected MayaCheckoutService $maya) {}

    public function store(StartMayaCheckoutRequest $request, Order $order): RedirectResponse
    {
        abort_unless(MayaCheckoutClient::enabled(), 404);

        $onlinePayment = $this->maya->start($order, $request->validated(), $request->user());

        return redirect()->away($onlinePayment->redirect_url);
    }

    /**
     * Where Maya sends the browser back. The ?result= Maya redirected with is
     * only a hint — the bill is finalized only on the status Maya's API reports.
     */
    public function handleReturn(Request $request, OnlinePayment $onlinePayment): RedirectResponse
    {
        return $this->syncAndRedirect($onlinePayment, $request->query('result'));
    }

    public function refresh(OnlinePayment $onlinePayment): RedirectResponse
    {
        return $this->syncAndRedirect($onlinePayment);
    }

    public function cancel(OnlinePayment $onlinePayment): RedirectResponse
    {
        try {
            $onlinePayment = $this->maya->cancel($onlinePayment);
        } catch (RuntimeException $e) {
            return redirect()->route('orders.show', $onlinePayment->order_id)
                ->with('error', __('Could not reach Maya to check the payment first. Try again in a moment.'));
        }

        return $this->redirectWithOutcome($onlinePayment);
    }

    /**
     * Maya's webhook. The body is never trusted for the outcome — it only
     * says which payment to look at; the status is re-read from Maya's API.
     * Always answers 200 so Maya doesn't keep retrying a payment we don't know.
     */
    public function webhook(Request $request): JsonResponse
    {
        $paymentId = $request->input('id');
        $reference = $request->input('requestReferenceNumber');

        $onlinePayment = OnlinePayment::query()
            ->where('provider', 'maya')
            ->where(fn ($q) => $q->where('checkout_id', $paymentId ?? '')->orWhere('request_reference_number', $reference ?? ''))
            ->first();

        if ($onlinePayment) {
            try {
                $this->maya->sync($onlinePayment);
            } catch (\Throwable $e) {
                Log::error('Maya webhook sync failed', ['online_payment_id' => $onlinePayment->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['received' => true]);
    }

    protected function syncAndRedirect(OnlinePayment $onlinePayment, ?string $hint = null): RedirectResponse
    {
        try {
            $onlinePayment = $this->maya->sync($onlinePayment);
        } catch (RuntimeException $e) {
            return redirect()->route('orders.show', $onlinePayment->order_id)
                ->with('error', __('Could not reach Maya to confirm the payment. Use "Check Maya payment" on the order in a moment.'));
        }

        // Maya can bring the guest back before its own status has caught up.
        if ($onlinePayment->status === OnlinePaymentStatus::Pending && $hint === 'success') {
            return redirect()->route('orders.show', $onlinePayment->order_id)
                ->with('status', __('Maya is still confirming the payment. Use "Check Maya payment" in a moment.'));
        }

        return $this->redirectWithOutcome($onlinePayment);
    }

    protected function redirectWithOutcome(OnlinePayment $onlinePayment): RedirectResponse
    {
        $redirect = redirect()->route('orders.show', $onlinePayment->order_id);
        $number = $onlinePayment->order->orderNumber();

        return match ($onlinePayment->status) {
            OnlinePaymentStatus::Paid => $redirect->with('status', __('Maya payment received. Order :number marked as paid.', ['number' => $number])),
            OnlinePaymentStatus::NeedsReview => $redirect->with('error', __('Maya payment received, but the bill needs review: :reason', ['reason' => $onlinePayment->error_message])),
            OnlinePaymentStatus::Pending => $redirect->with('status', __('The Maya checkout is still waiting for payment.')),
            OnlinePaymentStatus::Cancelled => $redirect->with('status', __('The Maya checkout was cancelled. The bill is still unpaid.')),
            default => $redirect->with('error', __('The Maya payment did not go through (:status). The bill is still unpaid.', ['status' => $onlinePayment->status->label()])),
        };
    }
}
