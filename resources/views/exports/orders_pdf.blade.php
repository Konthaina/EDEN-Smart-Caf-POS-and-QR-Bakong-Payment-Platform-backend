{{-- resources/views/exports/orders_pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Order PDF Report</title>
  <style>
    @page { size: A4 portrait; margin: 12mm 10mm; }
    *{ box-sizing: border-box }
    body{ font-family: Arial, sans-serif; color:#111; font-size:10px; margin:0; }
    h2{ margin:0 0 6px 0; font-size:14px }
    .muted{ color:#666; font-size:9px; }
    .header{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .brand-logo{ width:56px; height:56px; border-radius:6px; background:#eee; object-fit:contain }

    .summary{ width:100%; border-collapse:collapse; margin-top:4px }
    .summary th,.summary td{ border:1px solid #bbb; padding:4px 5px; text-align:left }
    .summary th{ background:#f2f2f2; font-weight:bold; white-space:nowrap }

    table{ width:100%; border-collapse:collapse; table-layout:fixed; margin-top:8px }
    th,td{ border:1px solid #999; padding:4px 5px; vertical-align:top; word-break:break-word }
    th{ background:#f2f2f2; font-weight:bold; line-height:1.15 }
    .num{ text-align:right; white-space:nowrap }
    .tight{ line-height:1.05 }
    tfoot td{ font-weight:bold; background:#fafafa }

    thead{ display: table-header-group; }
    tfoot{ display: table-row-group; }
    tr{ page-break-inside: avoid; }

    /* Signature block (single column, bottom-right) */
    .signatures{ margin-top:14px; page-break-inside: avoid; }
    .sig-table{ width:40%; margin-left:auto; border-collapse:collapse; }
    .sig-table th{ background:#fff; font-weight:bold; text-align:center; border:0; padding:4px 5px; }
    .sig-table td{ border:0; padding:6px 8px; }
    .sig-line{ height:34px; border-bottom:1px solid #999; }
    .center{ text-align:center; }
    .published-at{ text-align:right; margin-top:6px; font-size:9px; color:#666; }
  </style>
</head>
<body>

@php
    // Brand (optional)
    $brandName = function_exists('get_setting') ? (get_setting('shop_name', config('app.name'))) : config('app.name');
    $logoPath  = function_exists('get_setting') ? (get_setting('shop_logo')) : null;
    $logoFile  = $logoPath ? public_path('storage/'.ltrim($logoPath, '/')) : null;

    // Period (from controller or fallback to data min/max)
    $rangeFrom = isset($from) ? \Carbon\Carbon::parse($from) : optional($orders->min('created_at'));
    $rangeTo   = isset($to)   ? \Carbon\Carbon::parse($to)   : optional($orders->max('created_at'));
    $periodTxt = trim(($rangeFrom ? $rangeFrom->format('d M Y') : '—') . ' → ' . ($rangeTo ? $rangeTo->format('d M Y') : '—'));

    // Aggregates (robust casts)
    $gross         = $orders->sum(fn($o) => (float)($o->total_amount ?? 0));       // SUM total (tax-included)
    $vat           = $orders->sum(fn($o) => (float)($o->tax_amount ?? 0));         // SUM VAT
    $discountTotal = $orders->sum(fn($o) => (float)($o->discount_amount ?? 0));    // SUM discount
    $taxableBase   = $gross - $vat;                                                // pre-tax after discount
    $grossBefore   = $taxableBase + $discountTotal;                                // pre-discount base
    $count         = $orders->count();
    $aov           = $count ? ($gross / $count) : 0;

    // For footer total Gross Before
    $sumGrossBefore = $orders->sum(function($o){
        $total = (float)($o->total_amount ?? 0);
        $vat   = (float)($o->tax_amount ?? 0);
        $disc  = (float)($o->discount_amount ?? 0);
        return ($total - $vat) + $disc;
    });

    // Signature name (pass from controller if you have it)
    $preparedBy = $preparedBy ?? ($prepared_by ?? null);

    // Published date at bottom (override by passing $published_at)
    $publishedAt = isset($published_at) ? \Carbon\Carbon::parse($published_at) : now();
@endphp

<div class="header">
  @if($logoFile && file_exists($logoFile))
    <img class="brand-logo" src="{{ $logoFile }}" alt="Logo">
  @endif
  <div>
    <h2>Order Report — {{ $brandName }}</h2>
    <div class="muted">
      Period: {{ $periodTxt }} • Generated: {{ now()->format('d/m/Y H:i') }}
    </div>
  </div>
</div>

<table class="summary">
  <tr>
    <th>Gross collected (USD)</th>
    <th>VAT collected</th>
    <th>Taxable base</th>
    <th>Gross before discount</th>
    <th>Discount total</th>
    <th>Average order value</th>
    <th>Orders</th>
  </tr>
  <tr>
    <td class="num">{{ number_format($gross, 2) }}</td>
    <td class="num">{{ number_format($vat, 2) }}</td>
    <td class="num">{{ number_format($taxableBase, 2) }}</td>
    <td class="num">{{ number_format($grossBefore, 2) }}</td>
    <td class="num">{{ number_format($discountTotal, 2) }}</td>
    <td class="num">{{ number_format($aov, 2) }}</td>
    <td class="num">{{ $count }}</td>
  </tr>
</table>

<table>
  {{-- Current columns (no Discount %) --}}
  <colgroup>
    <col style="width:24%">  {{-- Order Code --}}
    <col style="width:13%">  {{-- Promo Code --}}
    <col style="width:12%">  {{-- Payment Method --}}
    <col style="width:14%">  {{-- Gross Before --}}
    <col style="width:10%">  {{-- Discount (money) --}}
    <col style="width:6%">   {{-- Tax % --}}
    <col style="width:7%">   {{-- VAT --}}
    <col style="width:8%">   {{-- Total --}}
    <col style="width:6%">   {{-- Date --}}
  </colgroup>

  <thead>
    <tr>
      <th>Order Code</th>
      <th>Promo Code</th>
      <th>Payment Method</th>
      <th class="num tight">Gross<br>Before</th>
      <th class="num">Discount</th>
      <th class="num">Tax %</th>
      <th class="num">VAT</th>
      <th class="num">Total</th>
      <th>Date</th>
    </tr>
  </thead>

  <tbody>
    @foreach ($orders as $order)
      @php
        $total     = (float) ($order->total_amount ?? 0);
        $vatAmt    = (float) ($order->tax_amount ?? 0);
        $discount  = (float) ($order->discount_amount ?? 0);
        $taxable   = $total - $vatAmt;         // after discount, before tax
        $grossBef  = $taxable + $discount;     // pre-discount base
        $rate      = $order->tax_rate ?? ($taxable > 0 ? ($vatAmt / $taxable) * 100 : 0);
      @endphp
      <tr>
        <td>{{ $order->order_code }}</td>
        <td>{{ $order->discount->code ?? '-' }}</td>
        <td>{{ ucfirst($order->payment_method ?? '-') }}</td>

        <td class="num">{{ number_format($grossBef, 2) }}</td>
        <td class="num">{{ number_format($discount, 2) }}</td>
        <td class="num">{{ number_format($rate, 2) }}%</td>
        <td class="num">{{ number_format($vatAmt, 2) }}</td>
        <td class="num">{{ number_format($total, 2) }}</td>
        <td>{{ optional($order->created_at)->format('d/m/Y') }}</td>
      </tr>
    @endforeach
  </tbody>

  <tfoot>
    <tr>
      <td colspan="3" class="num">Totals</td>
      <td class="num">{{ number_format($sumGrossBefore, 2) }}</td>
      <td class="num">{{ number_format($discountTotal, 2) }}</td>
      <td class="num">—</td>
      <td class="num">{{ number_format($vat, 2) }}</td>
      <td class="num">{{ number_format($gross, 2) }}</td>
      <td></td>
    </tr>
  </tfoot>
</table>

{{-- Signature block (bottom-right, Prepared by only) --}}
<div class="signatures">
  <table class="sig-table">
    <tr>
      <th>Prepared by</th>
    </tr>
    <tr>
      <td><div class="sig-line"></div></td>
    </tr>
    <tr>
      <td class="center">{{ $preparedBy ?: 'Name' }}</td>
    </tr>
    <tr class="muted">
      <td class="center">Date: {{ $publishedAt->format('d/m/Y') }}</td>
    </tr>
  </table>
  <div class="published-at">Published at: {{ $publishedAt->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
