<?php

namespace App\Http\Controllers;

use Exception;
use Stripe\Account;
use Stripe\Webhook;
use App\Models\Invoice;
use Stripe\AccountLink;
use Illuminate\Http\Request;
use App\Services\StripeService;

class StripeController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function onboardReceiver(Request $request)
    {
        $user = $request->user();

        if (!$user->stripe_account_id) {
            $accountId = $this->stripeService->createConnectedAccount($user);
            $user->update(['stripe_account_id' => $accountId]);
        }

        $accountLink = AccountLink::create([
            'account' => $user->stripe_account_id,
            'refresh_url' => route('stripe.onboard.retry', [], true), // Use absolute URL
            'return_url' => route('stripe.onboard.success', [], true), // Use absolute URL
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'url' => $accountLink->url
        ]);
    }

    public function createPaymentIntent(Request $request)
    {
        $invoice = Invoice::findOrFail($request->invoice_id);
        $receiver = $invoice->receiver;

        if (!$receiver->stripe_account_id) {
            return response()->json(['error' => 'Receiver not onboarded'], 400);
        }

        $paymentIntent = $this->stripeService->createPaymentIntent(
            $invoice,
            $receiver->stripe_account_id
        );

        $invoice->update([
            'stripe_payment_intent_id' => $paymentIntent->id
        ]);

        return response()->json([
            'clientSecret' => $paymentIntent->client_secret,
            'amount' => $paymentIntent->amount,
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentSuccess($paymentIntent);
                break;
                // Handle other events as needed
        }

        return response()->json(['status' => 'success']);
    }

    protected function handlePaymentSuccess($paymentIntent)
    {
        $invoice = Invoice::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($invoice) {
            $invoice->update(['payment_status' => 'paid']);

            // Optionally trigger transfer to bank here or use separate webhook
        }
    }

    public function onboardSuccess(Request $request)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json(['message' => 'login please']);
        }

        // You might want to verify the account status with Stripe
        try {
            $stripeAccount = Account::retrieve($request->user()->stripe_account_id);

            if ($stripeAccount->details_submitted) {
                // Account is fully onboarded
                return view('stripe.onboard-success', [
                    'message' => 'Your account has been successfully connected!'
                ]);
            } else {
                // Account not fully onboarded
                return redirect()->route('stripe.onboard.retry')
                    ->with('error', 'Please complete all required steps');
            }
        } catch (Exception $e) {
            return redirect()->route('stripe.onboard.retry')
                ->with('error', 'We couldn\'t verify your account status');
        }
    }

    public function onboardRetry(Request $request)
    {
        return view('stripe.onboard-retry', [
            'message' => $request->session()->get('error', 'Please try connecting your account again')
        ]);
    }
}
