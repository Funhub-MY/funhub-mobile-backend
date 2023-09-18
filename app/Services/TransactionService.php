<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class TransactionService {
    /**
     * Create new transaction
     *
     * @param Class $transactionable
     * @param Float $amount
     * @param string $gateway
     * @param int $user_id
     * @param string $payment_method
     * @param string $transaction_no
     * @return Transaction
     */
    public function create($transactionable, $amount, $gateway, $user_id, $payment_method = 'fpx', $transaction_no = null)
    {
        if ($transaction_no == null) {
            $transaction_no = $this->generateTransactionNo();
        }

        // check if transactionable has transactions relationship
        if (!method_exists($transactionable, 'transactions')) {
            throw new \Exception('Transactionable model must have transactions relationship');
        }

        // // check if transactionable has user_id attribute
        // if (!isset($transactionable->user_id)) {
        //     throw new \Exception('Transactionable model must have user_id attribute');
        // }

        $transaction = null;
        try {
            // ensure transaction_no is unique else re-generate, max tries 3
            $tries = 0;
            while ($transactionable->transactions()->where('transaction_no', $transaction_no)->exists() && $tries < 3) {
                $transaction_no = $this->generateTransactionNo();
                $tries++;
            }

            $transaction = $transactionable->transactions()->create([
                'transaction_no' => $transaction_no,
                'user_id' => $user_id,
                'amount' => $amount,
                'gateway' => $gateway,
                'status' => Transaction::STATUS_PENDING,
                'gateway_transaction_id' => 'N/A',
                'payment_method' => $payment_method,
            ]);

            Log::info('Transaction created', [
                'transaction' => $transaction->toArray()
            ]);
        } catch (Exception $e) {
            Log::error('Transaction create failed', [
                'error' => $e->getMessage()
            ]);
        }

        return $transaction;
    }

    /**
     * Update transaction status
     *
     * @param string $transaction_id
     * @param string $status
     * @return Transaction
     */
    public function updateTransactionStatus($transaction_id, $status)
    {
        $transaction = Transaction::find($transaction_id);

        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }

        $transaction->status = $status;
        $transaction->save();

        return $transaction;
    }

    /**
     * Generate unique transaction no
     *
     * @return string
     */
    private function generateTransactionNo()
    {
        // format : {YY}{MM}{Random 3 digits}{Random 5 letters and digits mixed}
        // example: 2308321A1B233
        return date('ym') . rand(100, 999) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
    }
}
