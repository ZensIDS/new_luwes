@extends('layouts.base')

@section('title', 'Marketplace')

@section('container')
    <!-- Page Introduction Wrapper -->
    <div class="page-style-a">
        <div class="container">
            <div class="page-intro">
                <h2>Cart</h2>
                <ul class="bread-crumb">
                    <li class="has-separator">
                        <i class="ion ion-md-home"></i>
                        <a href="{{ route('market.index') }}">Home</a>
                    </li>
                    <li class="is-marked">
                        <a href="{{ route('marketcart.index') }}">Cart</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Introduction Wrapper /- -->
    <section class="section-maker">
        <div class="container">
            <div class="box">
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
                            @foreach ($cartItems->sortBy('name') as $item)
                                <tr>
                                    <td>
                                        <div class="cart-anchor-image">
                                            <a href="{{ route('market.show', $item->model->id) }}">
                                                @if ($item->model)
                                                    <img class="img-thumbnail" src="{{ asset($item->model->pic) }}" alt="Product" width="100px">
                                                @endif
                                                <h6>{{ $item->name }}</h6>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cart-price">
                                            @currency($item->price)
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cart-quantity">
                                            <form id="update" action="{{ route('marketcart.update') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $item->id }}">
                                                <input type="number" class="quantity-text-field" name="quantity" value="{{ $item->quantity }}" min="1">
                                                <button type="submit" class="btn btn-secondary"><i class="fa fa-sync"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cart-price action-wrapper">
                                            <form id="remove" action="{{ route('marketcart.remove') }}" method="POST">
                                                @csrf
                                                <input type="hidden" value="{{ $item->id }}" name="id">
                                                <button type="submit" class="btn btn-secondary">@currency($item->price * $item->quantity) <i class="fa fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                <tr>
                                    <th colspan="3">Total</th>
                                    <th>Total: Rp. {{ number_format(Cart::session(auth()->id())->getTotal(), 0, ',', '.') }}</th>
                                </tr>
                        </tbody>
                    </table>
                </div><!-- /.box-body -->
                <div class="coupon-continue-checkout u-s-m-b-60">
                    <div class="button-area">
                        <a href="{{ route('market.index') }}" class="continue">Continue Shopping</a>
                        <a href="{{ route('market.checkout') }}" class="checkout">Proceed to Checkout</a>
                    </div>
                </div>
            </div><!-- /.box -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
