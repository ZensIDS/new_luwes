<!DOCTYPE html>
<html>

<head>
    <title>Pembelian</title>
    <style>
        /* Define your custom CSS styles here */
        body {
            font-family: Arial, sans-serif;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid black;
            padding: 10px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h1>Pembelian #{{ $pembelian->code }}</h1>
    <table>
        {{-- <tr> --}}
        {{-- <td>Barcode</td> --}}
        {{-- <td>{!! DNS1D::getBarcodeHTML($pembelian->code, 'C128') !!}</td> --}}
        {{-- </tr> --}}
        <tr>
            <td>Outlet</td>
            <td>{{ $pembelian->outlet->name }}</td>
        </tr>
        <tr>
            <td>Supplier</td>
            <td>{{ $pembelian->supplier->name }}</td>
        </tr>
        {{-- <tr> --}}
        {{-- <td>Total</td> --}}
        {{-- <td>@currency($pembelian->total)</td> --}}
        {{-- </tr> --}}
    </table>

    <h2>Stocks</h2>
    <table border="1">
        <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Qty</th>
                {{-- <th>Subtotal</th> --}}
                <th>Harga Jual</th>
                {{-- <th>Expired At</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach ($pembelian->stocks as $stock)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $stock->product->name }}</td>
                    <td>{{ $stock->qty }}</td>
                    {{-- <td>@currency($stock->subtotal)</td> --}}
                    <td>@currency($stock->product->harga_jual)</td>
                    {{-- <td>{{ $stock->expired_at->format('h:i a / d-M-Y') }}</td> --}}
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
