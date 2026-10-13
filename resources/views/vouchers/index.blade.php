@extends('layouts.master')

@section('title', 'Voucher')

@section('container')
<section class="content-header">
    <h1>Voucher <small>Voucher, rafaksi, dan bundle dalam satu daftar</small></h1>
</section>

<section class="content">
    <div class="box">
        <div class="box-header">
            <a href="{{ route('campaign.create', ['type' => 'voucher']) }}" class="btn btn-success"><i class="fa fa-plus"></i> Buat voucher / promo</a>
            <form id="voucher-label-print-form" method="GET" action="{{ route('cashier.print.vouchers') }}"
                target="_blank" style="display:inline-block; margin-left:8px;">
                <input type="hidden" name="print" value="1">
                <button type="submit" id="print-selected-vouchers" class="btn btn-primary" disabled>
                    <i class="fa fa-print"></i> Cetak voucher terpilih
                    (<span id="selected-vouchers-count">0</span>)
                </button>
            </form>
            <p class="help-block" style="margin:10px 0 0;">
                Pilih voucher, rafaksi, atau promo bundling untuk mencetak label.
            </p>
        </div>
        <div class="box-body">
            <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">
                <li role="presentation" class="active">
                    <a href="#voucher-rafaksi" aria-controls="voucher-rafaksi" role="tab" data-toggle="tab">
                        <i class="fa fa-ticket"></i> Voucher &amp; Rafaksi
                        <span class="badge" id="badge-voucher">…</span>
                    </a>
                </li>
                <li role="presentation">
                    <a href="#bundling" aria-controls="bundling" role="tab" data-toggle="tab">
                        <i class="fa fa-gift"></i> Bundling
                        <span class="badge" id="badge-bundle">…</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="voucher-rafaksi">
                    @include('vouchers._campaign-table', [
                        'tab' => 'voucher',
                        'tableId' => 'voucher-rafaksi-table',
                        'emptyMessage' => 'Belum ada voucher atau rafaksi.',
                        'allowPrintSelection' => true,
                    ])
                </div>
                <div role="tabpanel" class="tab-pane" id="bundling">
                    @include('vouchers._campaign-table', [
                        'tab' => 'bundle',
                        'tableId' => 'bundling-table',
                        'emptyMessage' => 'Belum ada bundling.',
                        'allowPrintSelection' => true,
                    ])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('page-script')
<script>
$(function () {
    var dataUrl = '{{ route('voucher.index.data') }}';
    var tables = {};

    $('#voucher-rafaksi-table, #bundling-table').each(function () {
        var $table = $(this);
        var tab = $table.data('tab');

        // Server-side: hanya satu halaman yang dikirim ke browser (aman untuk ribuan voucher).
        // Search berbasis awalan nama/kode/produk, diproses di server.
        tables[tab] = $table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: dataUrl,
                data: function (d) { d.tab = tab; },
                dataSrc: function (json) {
                    $('#badge-' + tab).text(json.recordsTotal);
                    return json.data;
                }
            },
            searchDelay: 400,
            pageLength: 25,
            order: [],
            columns: [
                { orderable: false, searchable: false, className: 'text-center' },
                { orderable: false, searchable: false,
                  render: function (d, t, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                null, // nama / kode (bisa diurutkan)
                { orderable: false },
                { orderable: false },
                { orderable: false },
                { orderable: false },
                null, // masa berlaku (bisa diurutkan)
                { orderable: false },
                { orderable: false },
                { orderable: false, className: 'text-nowrap' }
            ],
            language: {
                emptyTable: $table.data('empty-message') || 'Belum ada data.',
                searchPlaceholder: 'Awalan nama/kode/produk'
            },
            drawCallback: function () {
                $table.find('.voucher-select-all').prop('checked', false).prop('indeterminate', false);
                if (typeof updateSelectedVouchers === 'function') { updateSelectedVouchers(); }
            }
        });
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        $($(event.target).attr('href')).find('table').DataTable().columns.adjust();
    });

    function selectedVoucherGroups() {
        return $('.voucher-print-checkbox:enabled:checked');
    }

    function updateSelectedVouchers() {
        const selected = selectedVoucherGroups().length;
        $('#selected-vouchers-count').text(selected);
        $('#print-selected-vouchers').prop('disabled', selected === 0);

        $('.voucher-select-all').each(function () {
            const table = $(this).closest('table');
            const checks = table.find('tbody .voucher-print-checkbox:enabled');
            const checked = checks.filter(':checked').length;
            $(this).prop('checked', checks.length > 0 && checked === checks.length);
            $(this).prop('indeterminate', checked > 0 && checked < checks.length);
        });
    }

    $('.voucher-select-all').on('change', function () {
        const table = $(this).closest('table');
        table.find('tbody .voucher-print-checkbox:enabled').prop('checked', this.checked);
        updateSelectedVouchers();
    });

    $(document).on('change', '.voucher-print-checkbox', updateSelectedVouchers);

    $('#voucher-label-print-form').on('submit', function () {
        $(this).find('.voucher-print-hidden').remove();

        selectedVoucherGroups().each(function () {
            const checkbox = $(this);
            const ids = String(checkbox.data('voucher-ids') || '').split(',').filter(Boolean);
            const name = checkbox.data('campaign-type') === 'promotion'
                ? 'promotion_ids[]'
                : 'voucher_ids[]';

            ids.forEach(function (id) {
                $('<input>', {
                    type: 'hidden',
                    name: name,
                    value: id,
                    class: 'voucher-print-hidden'
                }).appendTo('#voucher-label-print-form');
            });
        });
    });

    updateSelectedVouchers();
});
</script>
@endsection