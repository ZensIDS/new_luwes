<div class="table-responsive">
    <table id="{{ $tableId }}" class="table table-bordered table-striped" style="width:100%"
        data-empty-message="{{ $emptyMessage }}" data-tab="{{ $tab }}">
        <thead>
            <tr>
                <th class="text-center">@if ($allowPrintSelection ?? true)<input type="checkbox" class="voucher-select-all" title="Pilih semua di halaman ini">@endif</th>
                <th>No</th>
                <th>Nama / Kode</th>
                <th>Jenis</th>
                <th>Produk</th>
                <th>Potongan</th>
                <th>Jumlah / kuota promo</th>
                <th>Masa berlaku</th>
                <th>Outlet</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>