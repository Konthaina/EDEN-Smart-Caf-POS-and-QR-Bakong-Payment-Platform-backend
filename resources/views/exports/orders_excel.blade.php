<table>
    <thead>
        <tr>
            <th>Order Code</th>
            <th>User ID</th>
            <th>Discount</th>
            <th>Promo Code</th>
            <th>Payment Method</th>
            <th>Total</th>
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
                <td>{{ $order->discount?->code ?? '-' }}</td>
                <td>{{ ucfirst($order->payment_method ?? '-') }}</td>
                <td>{{ number_format($order->total_amount, 2) }}</td>
                <td>{{ ucfirst($order->status) }}</td>
                <td>{{ $order->created_at->format('d/m/Y') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
