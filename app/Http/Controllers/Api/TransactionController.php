<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\Mpay;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected $mpay;
    public function __construct(Mpay $mpay)
    {
        $this->mpay = $mpay;
    }

    /**
     * Get all my transactions
     * Get all transactions of current logged in users paginated
     *
     * @param Request $request
     * @return void
     * 
     * @group Transaction
     * @bodyParam date_from string Date from Example: 2021-01-01
     * @bodyParam date_to string Date to Example: 2021-01-31
     * 
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        // get all my transactions
        $query = Transaction::where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'desc');

        // date range for query if request has date from and to
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->date_from)->startOfDay()->toDateTimeString(),
                Carbon::parse($request->date_to)->endOfDay()->toDateTimeString(),
            ]);
        }

        // get paginated transactions
        $transactions = $query->paginate(config('app.paginate_per_page'));

        return TransactionResource::collection($transactions);
    }

    /**
     * Get a Transaction By ID
     *
     * @param Transaction $transaction
     * @return Response
     * 
     * @group Transaction
     * @urlParam transaction required Transaction ID Example: 1
     * 
     * @response scenario=success {
     *  data: {}
     * }
     */
    public function show(Transaction $transaction)
    {
        // check if user is transaction owner first
        if ($transaction->user_id != auth()->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        return TransactionResource::make($transaction);
    }

    /**
     * Get Transaction by Transaction Number
     *
     * @param Request $request
     * @return void
     * 
     * @group Transaction
     * @response scenario=success {
     *  data: {}
     * }
     */
    public function getTransactionByNumber(Request $request)
    {
        $transaction = Transaction::where('transaction_no', $request->transaction_no)
            ->where('user_id', auth()->user()->id)
            ->first();

        if ($transaction) {
            return TransactionResource::make($transaction);
        }

        return response()->json([
            'message' => 'Transaction not found'
        ], 404);
    }
    
}
