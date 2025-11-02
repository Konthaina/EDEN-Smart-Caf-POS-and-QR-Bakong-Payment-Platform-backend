{{-- resources/views/exports/orders_excel.blade.php --}}
<table>
    {{-- ===== Title & Period ===== --}}
    @php
        use Illuminate\Support\Str;

        $brandName = function_exists('get_setting') ? (get_setting('shop_name', config('app.name'))) : config('app.name');

        // Period (from controller or fallback to data min/max)
        $rangeFrom = isset($from) ? \Carbon\Carbon::parse($from) : optional($orders->min('created_at'));
        $rangeTo   = isset($to)   ? \Carbon\Carbon::parse($to)   : optional($orders->max('created_at'));
        $periodTxt = trim(($rangeFrom ? $rangeFrom->format('d M Y') : '—') . ' → ' . ($rangeTo ? $rangeTo->format('d M Y') : '—'));

        // Aggregates for summary
        $gross         = $orders->sum(fn($o) => (float)($o->total_amount ?? 0.0));
        $discountTotal = $orders->sum(fn($o) => (float)($o->discount_amount ?? 0.0));
        $vat           = $orders->sum(fn($o) => (float)($o->tax_amount ?? 0.0));
        $taxableBase   = $gross - $vat;
        $grossBefore   = $taxableBase + $discountTotal;
        $count         = $orders->count();
        $aov           = $count ? ($gross / $count) : 0.0;

        // Derive ALL columns dynamically from the first row
        $first    = $orders->first();
        if ($first instanceof \Illuminate\Database\Eloquent\Model) {
            $columns = array_keys($first->getAttributes());
        } elseif (is_array($first)) {
            $columns = array_keys($first);
        } else {
            $columns = [];
        }

        // Helper: nice header label
        $label = fn(string $col) => ucwords(str_replace('_',' ', $col));

        // Helper: detect numeric money/tax columns by name
        $isMoney = fn(string $col) => (bool) preg_match('/(amount|total|price|vat|tax|discount|rate)$/i', $col);

        // Helper: render a single cell with simple formatting rules
        $renderCell = function($col, $val) use ($isMoney) {
            if (is_null($val)) return '<td></td>';

            // Date/timestamps (…_at)
            if (Str::endsWith($col, '_at')) {
                try {
                    $dt = \Carbon\Carbon::parse($val);
                    return '<td data-format="yyyy-mm-dd hh:mm">'.$dt->format('Y-m-d H:i').'</td>';
                } catch (\Throwable $e) {
                    return '<td>'.$val.'</td>';
                }
            }

            // Numeric money-ish columns
            if ($isMoney($col) && is_numeric($val)) {
                $num = (float)$val;
                return '<td data-format="#,##0.00">'.$num.'</td>';
            }

            // Plain
            if (is_scalar($val)) return '<td>'.$val.'</td>';

            // Fallback for unexpected structures
            return '<td>'.e(json_encode($val)).'</td>';
        };
    @endphp

    <thead>
        <tr>
            <th colspan="{{ max(count($columns), 9) }}" style="font-size:14px; font-weight:bold;">
                Orders Export — {{ $brandName }}
            </th>
        </tr>
        <tr>
            <th colspan="{{ max(count($columns), 9) }}" style="font-size:11px; color:#666;">
                Period: {{ $periodTxt }} • Generated: {{ now()->format('d/m/Y H:i') }}
            </th>
        </tr>
        <tr><th colspan="{{ max(count($columns), 9) }}"></th></tr>

        {{-- ===== Summary (compact) ===== --}}
        <tr style="background:#f3f4f6; font-weight:bold;">
            <th>Gross (USD)</th>
            <th>VAT</th>
            <th>Taxable base</th>
            <th>Gross before discount</th>
            <th>Discount total</th>
            <th>Avg order value</th>
            <th>Orders</th>
            <th colspan="{{ max(count($columns) - 7, 2) }}"></th>
        </tr>
        <tr>
            <td data-format="#,##0.00">{{ $gross }}</td>
            <td data-format="#,##0.00">{{ $vat }}</td>
            <td data-format="#,##0.00">{{ $taxableBase }}</td>
            <td data-format="#,##0.00">{{ $grossBefore }}</td>
            <td data-format="#,##0.00">{{ $discountTotal }}</td>
            <td data-format="#,##0.00">{{ $aov }}</td>
            <td data-format="0">{{ $count }}</td>
            <td colspan="{{ max(count($columns) - 7, 2) }}"></td>
        </tr>

        <tr><th colspan="{{ max(count($columns), 9) }}"></th></tr>

        {{-- ===== Dynamic header: ALL columns from orders table ===== --}}
        <tr style="background:#e5e7eb; font-weight:bold;">
            @forelse ($columns as $col)
                <th>{{ $label($col) }}</th>
            @empty
                <th>No columns</th>
            @endforelse
        </tr>
    </thead>

    <tbody>
        @forelse ($orders as $order)
            <tr>
                @foreach ($columns as $col)
                    @php
                        // Support both Eloquent models and arrays
                        $raw = is_array($order) ? ($order[$col] ?? null)
                             : ($order instanceof \Illuminate\Database\Eloquent\Model ? $order->getAttribute($col) : data_get($order, $col));
                    @endphp
                    {!! $renderCell($col, $raw) !!}
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ max(count($columns), 9) }}">No orders found for this period.</td>
            </tr>
        @endforelse
    </tbody>
</table>
