@extends('layouts.base')

@section('title', 'Marketplace')

@section('container')
    <!-- Page Introduction Wrapper -->
    <div class="page-style-a">
        <div class="container">
            <div class="page-intro">
                <h2>Wishlist</h2>
                <ul class="bread-crumb">
                    <li class="has-separator">
                        <i class="ion ion-md-home"></i>
                        <a href="{{ route('market.index') }}">Home</a>
                    </li>
                    <li class="is-marked">
                        <a href="{{ route('wishlist.index') }}">Wishlist</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Introduction Wrapper /- -->
    <section class="section-maker">
        <div class="container">
            <div class="box-body table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($items)
                            @foreach ($items as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('market.show', $item->id) }}" class="cart-name">
                                            {{ $item->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="cart-price">
                                            @currency($item->price)
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cart-quantity">
                                            {{ $item->quantity }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cart-price action-wrapper">
                                            <form id="remove" action="{{ route('wishlist.remove') }}" method="POST">
                                                @csrf
                                                <input type="hidden" value="{{ $item->id }}" name="id">
                                                <button type="submit" class="btn btn-secondary">@currency($item->price * $item->quantity) <i class="fa fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4">Kosong</td>
                            </tr>
                        @endif
                        @if ($total)
                            <tr>
                                <th colspan="3">Total</th>
                                <th>Total: @currency($total)</th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div><!-- /.box-body -->
            <div class="coupon-continue-checkout u-s-m-b-60">
                <div class="button-area">
                    <a href="{{ route('market.index') }}" class="continue">Continue Shopping</a>
                    <a href="{{ route('wishlist.move-to-cart') }}" class="checkout">Proceed to Cart</a>
                </div>
            </div>
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
