<table>
    <thead>
        <tr>
            <th>Code</th>
            <th>Outlet</th>
            <th>Supplier</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pembelians as $pembelian)
            <tr>
                <td>{{ $pembelian->code }}</td>
                <td>{{ $pembelian->outlet?->name }}</td>
                <td>{{ $pembelian->supplier?->name }}</td>
                <td>@currency($pembelian->total)</td>
            </tr>
        @endforeach
    </tbody>
</table>
