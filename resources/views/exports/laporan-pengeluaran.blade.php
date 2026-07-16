<table>
    <thead>
        <tr>
            <th>Category</th>
            <th>Tanggal</th>
            <th>Biaya</th>
            <th>Desc</th>
            <th>Kas</th>
            <th>Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pengeluarans as $pengeluaran)
            <tr>
                <td>{{ $pengeluaran->category->name }}</td>
                <td>{{ $pengeluaran->tanggal }}</td>
                <td>@currency($pengeluaran->biaya)</td>
                <td>{{ $pengeluaran->desc }}</td>
                <td>{{ $pengeluaran->kas->name }}</td>
                <td>{{ $pengeluaran->jumlah }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
