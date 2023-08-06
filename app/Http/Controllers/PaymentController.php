<?php

namespace App\Http\Controllers;

use App\Services\Mpay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $gateway; 
    
    public function __construct()
    {
        $this->gateway = new Mpay(
            config('services.mpay.mid'),
            config('services.mpay.hash_key')
        );
    }

    public function paymentReturn()
    {
        Log::info('Payment return', [
            'request' => request()->all()
        ]);
    }
}
