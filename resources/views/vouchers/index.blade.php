@extends('layouts.master')

@php
    $voucherFlashSale = $campaigns->filter(fn ($campaign) => $campaign->campaign_kind === 'voucher' || $campaign->type === 'flash_sale')->values();
    $bundles = $campaigns->filter(fn ($campaign) => $campaign->campaign_kind === 'promotion' && $campaign->type === 'bundle')->values();
@endphp

@section('title', 'Voucher')

@section('container')
<section class="content-header">
    <h1>Voucher <small>Voucher, flash sale, dan bundle dalam satu daftar</small></h1>
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
                Pilih voucher atau bundling. Semua kode voucher dalam satu grup akan dicetak satu label per kode.
            </p>
        </div>
        <div class="box-body">
            <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">
                <li role="presentation" class="active">
                    <a href="#voucher-flash-sale" aria-controls="voucher-flash-sale" role="tab" data-toggle="tab">
                        <i class="fa fa-ticket"></i> Voucher &amp; Flash Sale
                        <span class="badge">{{ $voucherFlashSale->count() }}</span>
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
                <div role="tabpanel" class="tab-pane active" id="voucher-flash-sale">
                    @include('vouchers._campaign-table', [
                        'campaigns' => $voucherFlashSale,
                        'tableId' => 'voucher-flash-sale-table',
                        'emptyMessage' => 'Belum ada voucher atau flash sale.',
                    ])
                </div>
                <div role="tabpanel" class="tab-pane" id="bundling">
                    @include('vouchers._campaign-table', [
                        'campaigns' => $bundles,
                        'tableId' => 'bundling-table',
                        'emptyMessage' => 'Belum ada bundling.',
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
    $('#voucher-flash-sale-table, #bundling-table').each(function () {
        $(this).DataTable({ pageLength: 25, order: [[0, 'asc']] });
    });

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        const table = $($(event.target).attr('href')).find('table').DataTable();
        table.columns.adjust();
    });

    function selectedVoucherGroups() {
        return $('.voucher-print-checkbox:checked');
    }

    function updateSelectedVouchers() {
        const selected = selectedVoucherGroups().length;
        $('#selected-vouchers-count').text(selected);
        $('#print-selected-vouchers').prop('disabled', selected === 0);

        $('.voucher-select-all').each(function () {
            const table = $(this).closest('table');
            const checks = table.find('tbody .voucher-print-checkbox');
            const checked = checks.filter(':checked').length;
            $(this).prop('checked', checks.length > 0 && checked === checks.length);
            $(this).prop('indeterminate', checked > 0 && checked < checks.length);
        });
    }

    $('.voucher-select-all').on('change', function () {
        const table = $(this).closest('table');
        table.find('tbody .voucher-print-checkbox').prop('checked', this.checked);
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
