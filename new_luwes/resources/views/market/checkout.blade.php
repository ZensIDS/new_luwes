@extends('layouts.base')

@section('title', 'Marketplace')

@section('container')
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/adminlte/plugins/select2/select2.min.css') }}">
    <!-- Page Introduction Wrapper -->
    <div class="page-style-a">
        <div class="container">
            <div class="page-intro">
                <h2>Checkout</h2>
                <ul class="bread-crumb">
                    <li class="has-separator">
                        <i class="ion ion-md-home"></i>
                        <a href="home.html">Home</a>
                    </li>
                    <li class="is-marked">
                        <a href="checkout.html">Checkout</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Introduction Wrapper /- -->
    <!-- Checkout-Page -->
    <div class="page-checkout u-s-p-t-80">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <!-- Second Accordion -->
                    @if ($cartItems->count() > 0)
                        <form action="{{ route('market.coupon') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="message-open u-s-m-b-24">
                                Have a coupon?
                                <strong>
                                    <a class="u-c-brand" data-toggle="collapse" href="#showcoupon">Click here to enter your
                                        code</a>
                                </strong>
                            </div>
                            <div class="collapse u-s-m-b-24" id="showcoupon">
                                <h6 class="collapse-h6">
                                    Enter your coupon code if you have one.
                                </h6>
                                <div class="coupon-field">
                                    <label class="sr-only" for="coupon-code">Apply Coupon</label>
                                    <input id="coupon-code" name="code" type="text" class="text-field" placeholder="Coupon Code">
                                    <button type="submit" class="button">Apply Coupon</button>
                                </div>
                            </div>
                        </form>
                    @endif
                    <!-- Second Accordion /- -->
                    <div class="row">
                        <!-- Billing-&-Shipping-Details -->
                        <div class="col-lg-6">
                            <h4 class="section-h4">Billing Details</h4>
                            <form id="checkout-form" method="POST" action="{{ route('market.checkout') }}">
                                @csrf
                                <label for="courier">Kurir:</label>
                                <select class="form-control select2" name="courier" id="courier" required>
                                    <option value="" disabled selected>Pilih Kurir</option>
                                    <option value="jne">JNE</option>
                                    <option value="pos">POS</option>
                                    <option value="tiki">TIKI</option>
                                </select>
                                <label for="province">Province:</label>
                                <select class="form-control select2" name="province" id="province" required>
                                    <option value="" disabled selected>Pilih Provinsi</option>
                                    @foreach ($provinces as $province)
                                        <option value="{{ $province['province_id'] }}">{{ $province['province'] }}</option>
                                    @endforeach
                                </select>

                                <label for="city">City:</label>
                                <select class="form-control select2" name="city" id="city" required></select>

                                {{-- <button class="mt-1 button button-outline-secondary" type="submit">Calculate Shipping Cost</button> --}}
                            </form>

                            <div class="u-s-m-b-13">
                                <label for="order_notes">Order Notes</label>
                                <textarea class="text-area" name="order_notes" placeholder="Notes about your order, e.g. special notes for delivery."></textarea>
                            </div>
                        </div>
                        <!-- Billing-&-Shipping-Details /- -->
                        <!-- Checkout -->
                        <div class="col-lg-6">
                            <h4 class="section-h4">Your Order</h4>
                            <form action="{{ route('market.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="order-table">
                                    <table class="u-s-m-b-13">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($cartItems->sortBy('name') as $item)
                                                <tr>
                                                    <td>
                                                        <h6 class="order-h6">{{ $item->name }}</h6>
                                                        <span class="order-span-quantity">x{{ $item->quantity }}</span>
                                                        @foreach ($item->conditions as $condition)
                                                            <span class="order-span-voucher">{{ $condition->getName() }}:
                                                                {{ $condition->getValue() }}</span>
                                                        @endforeach
                                                    </td>
                                                    <td>
                                                        @if ($item->getPriceSumWithConditions() != $item->price * $item->quantity)
                                                            <h6 class="order-h6"><s>@currency($item->price * $item->quantity)</s></h6>
                                                        @endif
                                                        <h6 class="order-h6">@currency($item->getPriceSumWithConditions())</h6>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>
                                                    <h3 class="order-h3">Subtotal</h3>
                                                </td>
                                                <td>
                                                    <h3 class="order-h3">
                                                        Rp.{{ number_format(Cart::session(auth()->id())->getSubTotal(), 0, ',', '.') }}
                                                    </h3>
                                                </td>
                                            </tr>
                                            @foreach (Cart::session(auth()->id())->getConditions() as $condition)
                                                <tr>
                                                    <td>
                                                        <h3 class="order-h3">{{ $condition->getName() }}</h3>
                                                    </td>
                                                    <td>
                                                        <h3 class="order-h3">
                                                            {{ strpos($value = $condition->getValue(), '%') !== false ? $value : 'Rp. ' . number_format($value, 0, ',', '.') }}
                                                        </h3>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @if (session('shipping_cost'))
                                                <tr><td>Destination</td><td>{{ session('destination') }}</td></tr>
                                                <tr><td>Courier</td><td>{{ session('courier') }}</td></tr>
                                                <tr><td>Weight</td><td>{{ session('weight') }} grams</td></tr>
                                            @endif
                                            <tr>
                                                <td>
                                                    <h3 class="order-h3">Total</h3>
                                                </td>
                                                <td>
                                                    <h3 class="order-h3">
                                                        Rp.{{ number_format(Cart::session(auth()->id())->getTotal(), 0, ',', '.') }}
                                                    </h3>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    @foreach ($payments as $item)
                                        <div class="u-s-m-b-13">
                                            <input type="radio" class="radio-box" name="payment_method"
                                                id="{{ $item->id }}" value="{{ $item->id }}"
                                                @checked($loop->first)>
                                            <label class="label-text"
                                                for="{{ $item->id }}">{{ $item->name }}</label>
                                            <label class="label-text"
                                                for="{{ $item->id }}">{{ $item->bank_number }}</label>
                                            <label class="label-text"
                                                for="{{ $item->id }}">{{ $item->desc }}</label>
                                        </div>
                                    @endforeach
                                    <button type="submit" class="button button-outline-secondary">Place Order</button>
                                </div>
                            </form>
                        </div>
                        <!-- Checkout /- -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Checkout-Page /- -->
@endsection
@section('page-script')
    <!-- Select2 -->
    <script src="{{ asset('assets/adminlte/plugins/select2/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $(".select2").select2();
            $('#city').prop('disabled', true);
            $('#province').on('change', function() {
                const provinceId = $(this).val();

                $.get(`/cities?province_id=${provinceId}`, function(data) {
                    $('#city').empty();
                    $('#city').prop('disabled', false);

                    $('#city').append(`<option value="" disabled selected>Pilih Kota</option>`);
                    data.forEach(city => {
                        $('#city').append(`<option value="${city.city_id}">${city.type} ${city.city_name}</option>`);
                    });
                });
            });
            $('#city').on('change', function() {
                const cityId = $(this).val();

                // Submit the form using jQuery
                $('#checkout-form').submit();
            });
        });
    </script>
@endsection
