<?php

namespace App\Http\Controllers;

use App\Models\OnlinePayment;
use App\Services\Doku;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DOKU's HTTP notification for a Checkout payment. Checks the signature,
 * settles the payment once, and answers 2xx so DOKU stops retrying.
 */
class DokuWebhookController extends Controller
{
    public function __invoke(Request $request, Doku $doku): Response
    {
        abort_unless($doku->verify($request), 401);

        $payment = OnlinePayment::query()->where('online_payments.invoice_number', (string) $request->json('order.invoice_number'))->first();

        // An invoice Lais never made will not become known by retrying, so it is answered too.
        if ($payment !== null) {
            $doku->apply($payment, (string) $request->json('transaction.status'), (int) $request->json('order.amount'), $request->json('channel.id'));
        }

        return response()->noContent();
    }
}
