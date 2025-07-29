<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Account;
use Stripe\Transfer;
use Stripe\PaymentIntent;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createConnectedAccount($user)
    {
        $account = Account::create([
            'type' => 'custom',
            'country' => 'US', // Change as needed
            'email' => $user->email,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ]);

        return $account->id;
    }

    public function createPaymentIntent($invoice, $receiverStripeAccountId)
    {
        $adminCommission = $invoice->amount * ($invoice->admin_commission / 100);
        $receiverAmount = $invoice->amount - $adminCommission;

        $paymentIntent = PaymentIntent::create([
            'amount' => $invoice->amount * 100, // in cents
            'currency' => 'usd',
            'application_fee_amount' => $adminCommission * 100,
            'transfer_data' => [
                'destination' => $receiverStripeAccountId,
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'receiver_amount' => $receiverAmount,
            ],
        ]);

        return $paymentIntent;
    }

    public function transferToBank($receiverStripeAccountId, $amount, $bankAccountId)
    {
        $transfer = Transfer::create([
            'amount' => $amount * 100,
            'currency' => 'usd',
            'destination' => $receiverStripeAccountId,
            'source_type' => 'bank_account',
            'source_transaction' => $bankAccountId,
        ]);

        return $transfer;
    }
}
