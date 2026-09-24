@extends('layouts.master')

@section('title', 'Open POS')

@section('body_class', 'sidebar-collapse')

@section('container')
    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    <section class="content-header">
        <h1>
            Open POS
        </h1>
    </section>

    <section class="content">
        <h1>{{ $outlet->name }}</h1>
        <div id="cart"></div>
    </section>
@endsection
@section('page-script')
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}
    <script>
        window.outlet = {!! json_encode($outlet) !!};
        window.user = {!! json_encode(auth()->user()) !!};
    </script>
@endsection
