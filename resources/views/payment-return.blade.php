<html>
<head>
    <title>Payment Return</title>
</head>
<body>
<script>
    console.log('Mpay Payment Return');
    window.flutter_inappwebview.callHandler('paymentData', {
        'success': {{ $success ? 1 : 0 }},
        'transaction_id': "{{ $transaction_id }}",
        'offer_claim_id': "{{ (isset($offer_claim_id)) ? $offer_claim_id : 'null' }}",
        'redemption_start_date': "{{ isset($redemption_start_date) ? $redemption_start_date : 'null' }}",
        'redemption_end_date': "{{ isset($redemption_end_date) ? $redemption_end_date : 'null' }}",
        @if(isset($promotion_code))
        'promotion_code': {!! json_encode($promotion_code) !!},
        'promotion_code_group': {!! json_encode($promotion_code_group) !!},
        'discount': {!! json_encode($discount) !!},
        @endif
    });
</script>
<b>Mpay Payment Return</b>
<br>
<b>Success: {{ $success ? 1 : 0 }}</b>
<br>
<b>{{ $message }}</b>

@if(isset($promotion_code))
<div style="margin-top: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; font-family: monospace;">
    <h3>Promotion Code Data:</h3>
    <pre style="background-color: #f5f5f5; padding: 10px; overflow: auto;">
{
    "promotion_code": {!! json_encode($promotion_code, JSON_PRETTY_PRINT) !!},
    "promotion_code_group": {!! json_encode($promotion_code_group, JSON_PRETTY_PRINT) !!},
    "discount": {!! json_encode($discount, JSON_PRETTY_PRINT) !!}
}
    </pre>
</div>
@endif
</body>
</html>
