# Payment Module Documentation

## Overview

The Payment module handles all payment-related operations in the FunHub Mobile Backend. It enables users to purchase FUNCARDs (gift cards) or claim merchant offers through integration with the MPAY payment gateway. The module manages the entire payment lifecycle, from initiating a transaction to processing callbacks and handling success or failure scenarios.

## User Stories

| As a | I want to | Acceptance Criteria |
|------|-----------|---------------------|
| User | Purchase a FUNCARD using a payment method of my choice (FPX, card, or e-wallet) | - I can select a FUNCARD product and proceed to checkout<br>- The system validates product availability and my eligibility<br>- I am redirected to the MPAY payment gateway to complete the payment<br>- Upon successful payment, I receive the appropriate rewards<br>- I can view my transaction history |
| User | Claim a merchant offer by paying with fiat currency | - I can browse and select merchant offers<br>- The system verifies offer availability and validity<br>- I can choose my preferred payment method (FPX, card, or e-wallet)<br>- After successful payment, I receive a voucher<br>- I can view my claimed offers |
| User | Claim a merchant offer using my points (FUNBOX) | - I can browse available merchant offers<br>- The system checks if I have sufficient points for the offer<br>- Upon confirmation, points are deducted from my balance<br>- I immediately receive a voucher without requiring payment gateway interaction<br>- I can view my point transaction history |
| User | Receive confirmation of my payment | - After payment completion, I am redirected back to the application<br>- The system displays a success or failure message<br>- For successful transactions, I receive notifications (email and/or in-app) with transaction details<br>- I can access my transaction receipt |
| User | Save and manage my payment methods | - I can save my card details for future use<br>- I can view my saved payment methods<br>- I can delete saved payment methods<br>- My payment information is securely stored |
| User | View my transaction history | - I can see all my past transactions<br>- I can filter transactions by type (FUNCARD, merchant offer)<br>- I can see the status of each transaction<br>- I can view transaction details including payment method and amount |

## Key Methods and Logic Flow

### 1. Initiating Payment

#### ProductController.postCheckout

![Product Checkout Sequence Diagram](images/payment_product_checkout_sequence.svg)

This method handles the checkout process for purchasing FUNCARDs:

1. **Validation**: Validates input parameters (product_id, payment_method, quantity).
2. **Eligibility Check**: Verifies that the user has a verified email address.
3. **Product Verification**: Checks if the product exists and is published.
4. **Quantity Check**: For limited supply products, verifies sufficient quantity is available.
5. **Transaction Creation**: Creates a pending transaction record.
6. **Payment Gateway Integration**: Generates the necessary data for the MPAY payment gateway.
7. **Inventory Management**: For limited supply products, reduces the available quantity.
8. **Response**: Returns gateway data to redirect the user to the payment page.

```php
// Example flow in ProductController.postCheckout
$transaction = $this->transactionService->create(
    $product,
    $net_amount,
    config('app.default_payment_gateway'),
    $user->id,
    ($walletType) ? $walletType : $request->fiat_payment_method,
    'app',
    ($request->has('email') ? $request->email : null),
    ($request->has('name') ? $request->name : null),
    ($request->has('referral_code') ? $request->referral_code : null),
);

$mpayData = $mpayService->createTransaction(
    $transaction->transaction_no,
    $net_amount,
    $transaction->transaction_no,
    secure_url('/payment/return'),
    $user->full_phone_no ?? null,
    $user->email ?? null,
    ($walletType) ? $walletType : null,
    $selectedCard ? $selectedCard->card_token : null,
    $user->id
);
```

#### MerchantOfferController.postClaimOffer
This method handles claiming merchant offers through two payment methods:

1. **Points Payment Flow**:
   - Validates offer availability and user's point balance.
   - Creates a claim record with CLAIM_SUCCESS status.
   - Deducts points from the user's balance.
   - Assigns a voucher to the user.
   - Sends notifications to the user.

2. **Fiat Payment Flow**:
   - Validates offer availability.
   - Creates a pending transaction record.
   - Creates a claim record with CLAIM_AWAIT_PAYMENT status.
   - Temporarily assigns a voucher to the user.
   - Generates MPAY gateway data for payment.
   - Returns gateway data to redirect the user to the payment page.

```php
// Example of fiat payment flow in MerchantOfferController.postClaimOffer
$transaction = $this->transactionService->create(
    $offer,
    ($discount_amount > 0) ? $amount : $net_amount,
    config('app.default_payment_gateway'),
    $user->id,
    ($walletType) ? $walletType : $request->fiat_payment_method,
    $request->get('channel', 'app'),
    $request->get('email', null),
    $request->get('channel') === 'funhub_web' ? $request->get('name') : $user->name,
    $request->get('referral_code'),
);

$mpayData = $mpayService->createTransaction(
    $transaction->transaction_no,
    $net_amount,
    $transaction->transaction_no,
    secure_url('/payment/return'),
    $user->full_phone_no ?? null,
    $user->email ?? null,
    $walletType,
    $selectedCard ? $selectedCard->card_token : null,
    $user->id
);
```

### 2. Payment Gateway Integration (Mpay Service)

The `Mpay` service handles all interactions with the MPAY payment gateway:

1. **createTransaction**: Generates the necessary data for initiating a payment transaction.
   - Creates a secure hash for the request.
   - Formats the amount according to MPAY requirements.
   - Prepares form data for the payment gateway.

2. **queryTransaction**: Checks the status of a transaction by invoice number.
   - Useful for verifying transaction status when callbacks are missed.

3. **checkAvailablePaymentTypes**: Retrieves available payment methods from MPAY.
   - Helps in displaying only supported payment options to users.

4. **Hash Generation**: Various methods for generating secure hashes for requests and validating responses.
   - `generateHashForRequest`: Creates a hash for payment requests.
   - `generateHashForResponse`: Creates a hash for validating payment responses.

```php
// Example of hash generation in Mpay service
public function generateHashForRequest($mid, $invoice_no, $amount)
{
    $string = strval($this->hashKey) . 'Continue' . $mid . $invoice_no . strval($amount);
    return $this->secureHash->generateSecureHash($string);
}
```

### 3. Handling Payment Callbacks and Returns

#### PaymentController.paymentReturn
This method processes the return from the payment gateway:

1. **Validation**: Verifies that all required parameters are present.
2. **Transaction Lookup**: Retrieves the transaction record using the invoice number.
3. **Hash Validation**: Validates the secure hash from the gateway to prevent tampering.
4. **Status Check**: Checks if the transaction has already been processed.
5. **Success Handling**: For successful payments:
   - Updates the transaction status to SUCCESS.
   - Updates related records (product or merchant offer).
   - Sends notifications to the user.
6. **Failure Handling**: For failed payments:
   - Updates the transaction status to FAILED.
   - Releases any reserved resources (e.g., vouchers).
7. **Response**: Returns an appropriate view or redirect based on the channel (app or web).

```php
// Example of success handling in PaymentController.paymentReturn
if ($request->responseCode == 0 || $request->responseCode == '0') {
    $transaction->update([
        'status' => \App\Models\Transaction::STATUS_SUCCESS,
        'gateway_transaction_id' => ($request->has('mpay_ref_no')) ? $request->mpay_ref_no : $request->authCode,
    ]);

    if ($transaction->transactionable_type == MerchantOffer::class) {
        $this->updateMerchantOfferTransaction($request, $transaction);
    } else if ($transaction->transactionable_type == Product::class) {
        $this->updateProductTransaction($request, $transaction);
    }
}
```

#### PaymentController.updateMerchantOfferTransaction
This method handles the post-payment processing for merchant offers:

1. **Success Flow**:
   - Updates the claim status to CLAIM_SUCCESS.
   - Sends notifications to the user.

2. **Failure Flow**:
   - Updates the claim status to CLAIM_FAILED.
   - Releases the voucher that was temporarily assigned.
   - Restores the offer quantity.

```php
// Example of failure handling in updateMerchantOfferTransaction
if ($claim->voucher_id) {
    $successfulClaim = MerchantOfferClaim::where('voucher_id', $claim->voucher_id)
        ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
        ->first();

    if (!$successfulClaim) {
        $voucher = MerchantOfferVoucher::where('id', $claim->voucher_id)
            ->where('owned_by_id', $claim->user_id)
            ->first();
            
        if ($voucher) {
            $voucher->owned_by_id = null;
            $voucher->save();
        }
    }
}
```

#### PaymentController.updateProductTransaction
This method handles the post-payment processing for FUNCARD purchases:

1. **Success Flow**:
   - Retrieves the product reward.
   - Credits the user with the reward points.
   - Sends a notification to the user.

2. **Failure Flow**:
   - Logs the failure for auditing purposes.

## Security Considerations

1. **Secure Hash Validation**: All payment responses are validated using secure hashes to prevent tampering.
   ```php
   protected function validateSecureHash($mid, $responseCode, $authCode, $invoice_no, $amount)
   {
       $secureHash = $this->gateway->generateHashForResponse($mid, $responseCode, $authCode, $invoice_no, $amount);
       return $secureHash == request()->securehash2;
   }
   ```

2. **Email Verification**: Users must have verified email addresses before making payments.
   ```php
   if (!auth()->user()->hasVerifiedEmail()) {
       return response()->json([
           'message' => __('messages.error.product_controller.Please_verify_your_email_address_first')
       ], 422);
   }
   ```

3. **HTTPS for Payment URLs**: All payment gateway URLs use HTTPS to ensure secure communication.
   ```php
   if (substr($redirectUrl, 0, 4) === 'http') {
       $redirectUrl = str_replace('http://', 'https://', $redirectUrl);
   }
   ```

4. **Input Validation**: All input parameters are validated before processing.
   ```php
   $request->validate([
       'product_id' => 'required|integer',
       'payment_method' => 'required',
       'fiat_payment_method' => 'required_if:payment_method,fiat,in:fpx,card',
       'card_id' => 'exists:user_cards,id',
       'quantity' => 'required|integer|min:1',
       'referral_code' => 'nullable|string'
   ]);
   ```

5. **Transaction Idempotency**: The system prevents duplicate processing of the same transaction.
   ```php
   if ($transaction->status != \App\Models\Transaction::STATUS_PENDING) {
       Log::info('Payment return/callback already processed', [
           'error' => 'Transaction already processed',
           'request' => request()->all()
       ]);
   }
   ```

## Performance Considerations

1. **Asynchronous Notifications**: Notifications are sent asynchronously to avoid delaying the payment response.
   ```php
   try {
       $transaction->user->notify(new OfferClaimed($merchantOffer, $transaction->user, 'fiat', $transaction->amount));
   } catch (Exception $ex) {
       Log::error('Failed to send notification', [
           'error' => $ex->getMessage(),
           'transaction_id' => $transaction->id,
       ]);
   }
   ```

2. **Efficient Database Queries**: The system uses optimized queries to retrieve transaction and related data.
   ```php
   $claim = MerchantOfferClaim::where('merchant_offer_id', $merchantOffer->id)
       ->where('user_id', $transaction->user_id)
       ->where('status', MerchantOffer::CLAIM_AWAIT_PAYMENT)
       ->latest()
       ->first();
   ```

3. **Transaction Locking**: The system uses database transactions to ensure data consistency during payment processing.

4. **Comprehensive Logging**: All payment operations are logged for monitoring and debugging purposes.
   ```php
   Log::info('Payment return/callback success', [
       'transaction_id' => $transaction->id,
       'request' => request()->all()
   ]);
   ```

5. **Error Handling**: The system includes robust error handling to prevent failures from affecting the user experience.
   ```php
   try {
       // Payment processing logic
   } catch (Exception $e) {
       Log::error('Payment processing error', [
           'error' => $e->getMessage(),
           'transaction_id' => $transaction->id
       ]);
   }
   ```

## Integration with Other Modules

1. **Product Module**: The Payment module integrates with the Product module to handle FUNCARD purchases.

2. **Merchant Offer Module**: The Payment module integrates with the Merchant Offer module to handle offer claims.

3. **Point Module**: The Payment module integrates with the Point module to handle point-based transactions and rewards.

4. **Notification Module**: The Payment module uses the Notification module to send payment confirmations to users.

## Conclusion

The Payment module provides a secure and efficient way for users to purchase FUNCARDs and claim merchant offers. By integrating with the MPAY payment gateway, it supports various payment methods (FPX, card, e-wallet) and handles the entire payment lifecycle, from initiation to completion. The module includes comprehensive error handling and security measures to ensure reliable payment processing.
