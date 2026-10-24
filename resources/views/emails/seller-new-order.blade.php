<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Order Received</title>
</head>
<body style="font-family: sans-serif; background-color: #f4f4f5; margin: 0; padding: 24px; color: #18181b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e4e4e7;">
        <h1 style="font-size: 20px; font-weight: bold; margin-top: 0; color: #18181b;">
            New Order Received!
        </h1>
        <p style="font-size: 14px; color: #52525b; line-height: 1.5;">
            You have received a new package for order <strong>#{{ $sellerOrder->order->order_number }}</strong>.
        </p>

        <div style="background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 16px; margin: 24px 0;">
            <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: bold;">Package Details</p>
            <p style="margin: 0; font-size: 13px; color: #52525b;">Order Value: <strong>₹{{ number_format($sellerOrder->grand_total / 100) }}</strong></p>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #52525b;">Items Count: {{ $sellerOrder->items->count() }}</p>
        </div>

        <a href="{{ $viewUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: bold;">
            View in Seller Portal
        </a>

        <p style="margin-top: 32px; font-size: 12px; color: #a1a1aa; border-top: 1px solid #f4f4f5; padding-top: 16px;">
            Please confirm and pack this package promptly to meet dispatch SLAs.
        </p>
    </div>
</body>
</html>

