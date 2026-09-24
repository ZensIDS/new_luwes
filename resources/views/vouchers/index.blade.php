@extends('layouts.master')

@php
    $voucherRafaksi = $campaigns->filter(fn ($campaign) => $campaign->campaign_kind === 'voucher' || $campaign->type === 'flash_sale')->values();
    $bundles = $campaigns->filter(fn ($campaign) => $campaign->campaign_kind === 'promotion' && $campaign->type === 'bundle')->values();
@endphp

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
                        <span class="badge">{{ $voucherRafaksi->count() }}</span>
                    </a>
                </li>
                <li role="presentation">
                    <a href="#bundling" aria-controls="bundling" role="tab" data-toggle="tab">
                        <i class="fa fa-gift"></i> Bundling
                        <span class="badge">{{ $bundles->count() }}</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="voucher-rafaksi">
                    @include('vouchers._campaign-table', [
                        'campaigns' => $voucherRafaksi,
                        'tableId' => 'voucher-rafaksi-table',
                        'emptyMessage' => 'Belum ada voucher atau rafaksi.',
                        'allowPrintSelection' => true,
                    ])
                </div>
                <div role="tabpanel" class="tab-pane" id="bundling">
                    @include('vouchers._campaign-table', [
                        'campaigns' => $bundles,
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
    $('#voucher-rafaksi-table, #bundling-table').each(function () {
        $(this).DataTable({ pageLength: 25, order: [[0, 'asc']] });
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        const table = $($(event.target).attr('href')).find('table').DataTable();
        table.columns.adjust();
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
