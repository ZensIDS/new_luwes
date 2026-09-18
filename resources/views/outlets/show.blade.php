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
        window.outlet = @json($outlet);
        window.POS_OUTLET_ID = @json($outlet->id);
        window.POS_PRODUCTS_URL = @json(route('outlet.products', ['outlet' => $outlet->id]));
        window.user = @json(auth()->user());
    </script>
    <style>
        html:has(body.pos-mode), body.pos-mode { height:100%; overflow:hidden; }
        .pos-mode .wrapper { height:100vh; min-height:0; overflow:hidden; }
        .pos-mode .main-header, .pos-mode .main-sidebar, .pos-mode .main-footer { display:none !important; }
        .pos-mode { background:#f4f6f9; }
        body.pos-mode.sidebar-collapse .content-wrapper, body.pos-mode.sidebar-mini.sidebar-collapse .content-wrapper { margin-left:0 !important; min-height:0 !important; height:100vh; overflow:hidden; background:#f4f6f9; }
        .pos-mode .content { height:100%; min-height:0 !important; padding:15px; overflow:hidden; background:#f4f6f9; display:flex; flex-direction:column; }
        .pos-header { flex:0 0 auto; display:flex; justify-content:space-between; align-items:center; min-height:46px; margin-bottom:12px; padding:12px 16px; background:#fff; border-left:4px solid #605ca8; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:16px; }
        .pos-header span { font-size:12px; color:#777; }
        #cart { flex:1 1 auto; min-height:0; overflow:hidden; }
        #cart > .pos-cashier-layout, #cart > .pos-cashier-layout > .pos-cashier-column { height:100%; min-height:0; }
        #cart > .pos-cashier-layout > .pos-cashier-column { display:flex; flex-direction:column; }
        .pos-cashier-details { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
        .pos-cashier-details > .table-responsive:first-child { flex:1 1 0; min-height:0; max-height:none !important; overflow-y:auto !important; }
        .pos-cashier-details > .table-responsive:nth-child(2), .pos-cashier-details > .row, .pos-cashier-details > p, .pos-cashier-details > .alert { flex:0 0 auto; }
        .pos-cashier-details > .row { margin-bottom:0; }
        .pos-cashier-details > .table-responsive:nth-child(2) table { margin-bottom:0; }
        .pos-cashier-details tr[tabindex="0"]:focus, .pos-mode tr[tabindex="0"]:focus { outline:2px solid #605ca8; outline-offset:-2px; }
    </style>
@endsection
