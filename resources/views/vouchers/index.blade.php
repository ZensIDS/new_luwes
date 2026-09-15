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
});
</script>
@endsection
