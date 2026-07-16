@extends('layouts.master')

@section('title', 'Pilih Outlets')

{{-- @section('body_class', 'sidebar-collapse') --}}

@section('container')
    <style>
        .gap-2 {
            display: flex;
            justify-content: center;
        }
    </style>

    <section class="content-header">
        <h1>
            Pilih Outlets
        </h1>
    </section>

    <section class="content">
        @foreach ($outlets as $outlet)
            <div class="gap-2 col-lg-12 col-xs-12">
                <a href="{{ route('outlet.show', $outlet->id) }}">
                    <div class="text-center">
                        <div class="small-box bg-yellow-gradient">
                            <img class="img-thumbnail" src="{{ asset($outlet->logo) }}" alt="" width="200px">
                            <p>{{ $outlet->name }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
        {{-- <div id="cart"></div> --}}
    </section>
@endsection
