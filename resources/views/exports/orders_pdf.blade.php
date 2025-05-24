<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Order PDF Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <h2>Order Report</h2>
    <table>
        <thead>
            <tr>
                <th>Order Code</th>
                <th>User ID</th>
                <th>Discount</th>
                <th>Promo Code</th>
                <th>Payment Method</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td>{{ $order->order_code }}</td>
                    <td>{{ $order->user_id }}</td>
                    <td>{{ number_format($order->discount_amount, 2) }}</td>
                    <td>{{ $order->discount->code ?? '-' }}</td>
                    <td>{{ ucfirst($order->payment_method ?? '-') }}</td>
                    <td>{{ number_format($order->total_amount, 2) }}</td>
                    <td>{{ ucfirst($order->status) }}</td>
                    <td>{{ $order->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
