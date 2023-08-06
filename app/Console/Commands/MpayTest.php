<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MpayTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mpay:test {amount} {invoice_no} {phone} {email}';

    protected $mpay;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Mpay Gateway';
    
    public function __construct()
    {
        parent::__construct();

        $this->mpay = new \App\Services\Mpay(
            config('services.mpay.mid'),
            config('services.mpay.hash_key')
        );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $amount = $this->argument('amount');
        $invoice = $this->argument('invoice_no');
        $phone = $this->argument('phone');
        $email = $this->argument('email');

        $this->info('Running Mpay test with data: '. $amount .' '. $invoice .' '. $phone .' '. $email);

        $response = $this->mpay->createTransaction(
            $invoice,
            $amount,
            'Test',
            app('url')->to('/'),
            $phone,
            $email
        );
        dd($response);

        return Command::SUCCESS;
    }
}
