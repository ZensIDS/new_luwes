<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sale No : {{ $penjualan->code }}</title>
</head>
<style type="text/css" media="all">
    body {
        max-width: 400px;
        margin: 0 auto;
        text-align: center;
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
    }

    #wrapper {
        min-width: 250px;
        margin: 0px auto;
    }

    #wrapper img {
        max-width: 300px;
        width: auto;
    }

    h2,
    h3,
    p {
        margin: 5px 0;
    }

    .left {
        width: 100%;
        float: right;
        text-align: right;
        margin-bottom: 3px;
        margin-top: 3px;
    }

    .right {
        width: 40%;
        float: right;
        text-align: right;
        margin-bottom: 3px;
    }

    .table,
    .totals {
        width: 100%;
        margin: 10px 0;
    }

    .table th {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .table td {
        padding: 0;
    }

    .totals td {
        width: 24%;
        padding: 0;
    }

    .table td:nth-child(2) {
        overflow: hidden;
    }

    @media print {
        body {
            text-transform: uppercase;
        }

        #buttons {
            display: none;
        }

        #wrapper {
            width: 100%;
            margin: 0;
            font-size: 9px;
        }

        #wrapper img {
            max-width: 300px;
            width: 80%;
        }

        #bkpos_wrp {
            display: none;
        }
    }
</style>

<body>
    @if ($penjualan->outlet)
        <table border="0" style="border-collapse: collapse; width: 100%; height: auto;">
            <tr>
                <td width="100%" align="center">
                    <center>
                        <img src="{{ asset($penjualan->outlet->logo) }}" style="width: 60px;" />
                    </center>
                </td>
            </tr>
            <tr>
                <td width="100%" align="center">
                    <h2 style="padding-top: 0px; font-size: 24px;"><strong>{{ $penjualan->outlet->name }}</strong></h2>
                </td>
            </tr>
            <tr>
                <td width="100%">
                    <span class="left" style="text-align: left;">
                        Alamat Outlet : {{ $penjualan->outlet->alamat }}
                    </span>
                    <span class="left" style="text-align: left;">
                        Dibuat : {{ $penjualan->created_at->format('Y-m-d') }}
                    </span>
                    <span class="left" style="text-align: left;">
                        Nama Customer : {{ $penjualan->customer->name }}
                    </span>
                    <span class="left" style="text-align: left;">{{ $penjualan->outlet->desc }}</span>
                </td>
            </tr>
        </table>
    @endif

    <div style="clear:both;"></div>

    <table class="table" cellspacing="0" border="0"
        style="margin-bottom:5px; border-top: 1px solid #000; border-collapse: collapse;">
        <thead>
            <tr>
                <th width="10%"><em>#</em></th>
                <th width="35%" align="left">Nama Produk</th>
                <th width="10%">Banyak</th>
                <th width="25%">Harga</th>
                <th width="20%" align="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $totalCost = 0; @endphp
            @foreach ($penjualan->items as $item)
                <tr>
                    <td style="text-align:center; width:30px;" valign="top">{{ $loop->iteration }}</td>
                    <td style="text-align:left; width:130px; padding-bottom: 10px" valign="top"> {{ $item->product->name }}</td>
                    <td style="text-align:center; width:50px;" valign="top">{{ $item->qty }}</td>
                    <td style="text-align:center; width:50px;" valign="top">@currency($item->price)</td>
                    <td style="text-align:right; width:70px;" valign="top">@currency($item->qty * $item->price)</td>
                </tr>
            @php $totalCost += $item->qty * $item->price; @endphp
            @endforeach
            <tr style="border-top: 1px solid #000; border-collapse: collapse;">
                <td style="text-align:center; width:30px;" valign="top">{{ $penjualan->items->count() }}</td>
                <td style="text-align:left; width:130px; padding-bottom: 10px" valign="top"></td>
                <td style="text-align:center; width:50px;" valign="top">{{ $penjualan->items->sum('qty') }}</td>
                <td style="text-align:center; width:50px;" valign="top">@currency($penjualan->items->sum('price'))</td>
                <td style="text-align:right; width:70px;" valign="top">@currency($penjualan->items->reduce(function ($carry, $item) { return $carry + $item->qty * $item->price; }, 0))</td>
            </tr>
        </tbody>
    </table>

    <table class="table" cellspacing="0" border="0" style="margin-bottom:5px; border-top: 1px solid #000; border-collapse: collapse;">
        <tbody>
            <tr>
                <td style="text-align:left; padding-top: 5px;"></td>
                <td style="text-align:right; padding-right:1.5%; border-right: 1px solid #000;font-weight:bold;"></td>
                <td style="text-align:left; padding-left:1.5%;"></td>
                <td style="text-align:right;font-weight:bold;">@currency($totalCost)</td>
            </tr>

            <tr>
                <td style="text-align:left; padding-top: 5px;"></td>
                <td style="text-align:right; padding-right:1.5%; border-right: 1px solid #000;font-weight:bold;"></td>
                <td style="text-align:left; padding-left:1.5%;">Diskon</td>
                <td style="text-align:right;font-weight:bold;">-@currency($penjualan->discount)</td>
            </tr>

            <tr>
                <td colspan="2" style="text-align:left; font-weight:bold; border-top:1px solid #000; padding-top:5px;">Total Keseluruhan</td>
                <td colspan="2" style="border-top:1px solid #000; padding-top:5px; text-align:right; font-weight:bold;"> @currency($totalCost - $penjualan->discount)</td>
            </tr>

            <tr>
                <td style="text-align:left; padding-top: 5px;"></td>
                <td style="text-align:right; padding-right:1.5%; border-right: 1px solid #000;font-weight:bold;"></td>
                <td style="text-align:left; padding-left:1.5%;">Total Dibayar</td>
                <td style="text-align:right;font-weight:bold;">@currency($penjualan->total)</td>
            </tr>

            <tr>
                <td style="text-align:left; padding-top: 5px;"></td>
                <td style="text-align:right; padding-right:1.5%; border-right: 1px solid #000;font-weight:bold;"></td>
                <td style="text-align:left; padding-left:1.5%;">kembali</td>
                <td style="text-align:right;font-weight:bold;">
                    @php $kembali = abs(($totalCost - $penjualan->discount) - $penjualan->total); @endphp
                    @currency($kembali)
                </td>
            </tr>

            <tr>
                <td style="text-align:left; padding-top: 5px; font-weight: bold; border-top: 1px solid #000;">
                    Metode Pembayaran
                </td>
                <td style="text-align:right; padding-top: 5px; padding-right:1.5%; border-top: 1px solid #000;font-weight:bold;"
                    colspan="3">
                    @if ($penjualan->transaction)
                        {{ $penjualan->transaction->payment->name }}.
                        {{ $penjualan->transaction->payment->bank_number }}
                    @else
                        {{ $penjualan->kas->name }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    @if ($penjualan->outlet)
        <div style="border-top:1px solid #000; padding-top:10px;">
            {!! $penjualan->outlet->footer !!}
        </div>
    @endif

    <div id="bkpos_wrp">
        <a href="{{ route('penjualan.index') }}"
            style="width:100%; display:block; font-size:12px; text-decoration: none; text-align:center; color:#FFF; background-color:#005b8a; border:0px solid #007FFF; padding: 10px 1px; margin: 5px auto 10px auto; font-weight:bold;">
            Kembali
        </a>
    </div>

    <div id="bkpos_wrp">
        <button type="button" onClick="window.print();return false;"
            style="width:101%; cursor:pointer; font-size:12px; background-color:#FFA93C; color:#000; text-align: center; border:1px solid #FFA93C; padding: 10px 0px; font-weight:bold;">
            Print Small
        </button>
    </div>

    </div>
</body>

</html>
