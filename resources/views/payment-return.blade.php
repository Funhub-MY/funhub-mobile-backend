<html>
<head>
    <title>Payment Return</title>
</head>
<body>
<script>
    window.flutter_inappwebview.callHandler('paymentData', {
        'success': {{ $success }},
        'transaction_id': "{{ $transaction_id }}"
        'offer_claim_id': "{{ (isset($offer_claim_id)) ? $offer_claim_id : '' }}",
        'redemption_start_date': "{{ isset($redemption_start_date) ? $redemption_start_date : '' }}",
        'redemption_end_date': "{{ isset($redemption_end_date) ? $redemption_end_date : '' }}",
    });
</script>
<b>{{ $message }}</b>
</body>
</html>
