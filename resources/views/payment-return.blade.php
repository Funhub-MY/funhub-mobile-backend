<html>
<head>
    <title>Payment Return</title>
</head>
<body>
<script>
    window.flutter_inappwebview.callHandler('paymentData', {
        'success': {{ $success }}, 
        'transaction_id': "{{ $transactionId }}" 
    });
</script>
<b>{{ $message }}</b>
</body>
</html>