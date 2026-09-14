@extends('layouts.master')

@section('title', $outlet->name . ' POS')

@section('body_class', 'sidebar-collapse pos-mode')

@section('container')
    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    <section class="content">
        <div class="pos-header"><strong>{{ $outlet->name }}</strong><span>F2 Search · F3 Scan · F8 Voucher · F10 Process</span></div>
        <div id="cart"></div>
    </section>
@endsection
@section('page-script')
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}
    <script>
        window.outlet = {!! json_encode($outlet) !!};
        window.user = {!! json_encode(auth()->user()) !!};
    </script>
    <style>
        .pos-mode .main-header, .pos-mode .main-sidebar, .pos-mode .main-footer { display:none; }
        .pos-mode .content-wrapper { margin-left:0; min-height:100vh; background:#f4f6f9; }
        .pos-mode .content { padding:15px; }
        .pos-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:12px 16px; background:#fff; border-left:4px solid #605ca8; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:16px; }
        .pos-header span { font-size:12px; color:#777; }
    </style>
@endsection
