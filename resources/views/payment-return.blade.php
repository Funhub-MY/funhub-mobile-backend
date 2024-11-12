@php
    Illuminate\Support\Facades\Log::info('is redemption start date isset' . isset($redemption_start_date));
@endphp
<html>
<head>
    <title>Payment Return</title>
</head>
<body>
<script>
    console.log('Mpay Payment Return');
    console.log('Passed Data:', {
        success: {{ $success ? 1 : 0 }},
        transaction_id: "{{ $transaction_id }}",
        offer_claim_id: "{{ isset($offer_claim_id) ? $offer_claim_id : 'null' }}",
        redemption_start_date: "{{ isset($redemption_start_date) ? $redemption_start_date : 'null' }}",
        redemption_end_date: "{{ isset($redemption_end_date) ? $redemption_end_date : 'null' }}"
    });
    window.flutter_inappwebview.callHandler('paymentData', {
        'success': {{ $success ? 1 : 0 }},
        'transaction_id': "{{ $transaction_id }}",
        'offer_claim_id': "{{ (isset($offer_claim_id)) ? $offer_claim_id : 'null' }}",
        'redemption_start_date': "{{ isset($redemption_start_date) ? $redemption_start_date : 'null' }}",
        'redemption_end_date': "{{ isset($redemption_end_date) ? $redemption_end_date : 'null' }}",
    });
</script>
<b>Mpay Payment Return</b>
<br>
<b>Success: {{ $success ? 1 : 0 }}</b>
<br>
<b>{{ $message }}</b>
<br>
<b>Redemption Start Date: {{ $redemption_start_date }}</b>
<br>
<b>Redemption End Date: {{ $redemption_end_date }}</b>
</body>
</html>
