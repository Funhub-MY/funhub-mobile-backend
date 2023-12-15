<html>
<head>
    <title>Payment Return</title>
</head>
<body>
<script>
    window.flutter_inappwebview.callHandler('paymentData', {
        'success': {{ $success }},
        'transaction_id': "{{ $transaction_id }}"
    });
</script>
<b>{{ $message }}</b>
</body>
</html>
