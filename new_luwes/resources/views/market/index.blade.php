@extends('layouts.base')

@section('title', 'Marketplace')

@section('container')
    <!-- Men-Clothing -->
    <section class="section-maker">
        <div class="container">
            <div class="text-center sec-maker-header">
                <ul class="nav tab-nav-style-1-a justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#men-latest-products">Sliders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#men-best-selling-products">Best Selling</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#men-top-rating-products">Top Rating</a>
                    </li>
                </ul>
            </div>
            <div class="wrapper-content">
                <div class="outer-area-tab">
                    <div class="tab-content">
                        <div class="tab-pane active show fade" id="men-latest-products">
                            <div class="slider-fouc">
                                <div class="products-slider owl-carousel" data-item="1">
                                    @foreach ($sliders as $slider)
                                        <div class="item">
                                            <div class="image-container">
                                                <a class="item-img-wrapper-link" href="#">
                                                    <img class="img-fluid" src="{{ asset($slider->pic) }}" alt="Product">
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="men-best-selling-products">
                            <div class="slider-fouc">
                                <div class="products-slider owl-carousel" data-item="2">
                                    @foreach ($bestSellingProducts as $best)
                                        <div class="item">
                                            <div class="image-container">
                                                <a class="item-img-wrapper-link" href="{{ route('market.show', $best->id) }}">
                                                    <img class="img-fluid" src="{{ asset($best->pic) }}" alt="Product">
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="men-top-rating-products">
                            <div class="slider-fouc">
                                <div class="products-slider owl-carousel" data-item="2">
                                    @foreach ($topRatedProducts as $top)
                                        <div class="item">
                                            <div class="image-container">
                                                <a class="item-img-wrapper-link" href="{{ route('market.show', $top->id) }}">
                                                    <img class="img-fluid" src="{{ asset($top->pic) }}" alt="Product">
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Men-Clothing-Timing-Section -->
    <section class="section-maker">
        <div class="container">
            <!-- Carousel -->
            @if ($products->count() > 0)
                <div class="slider-fouc">
                    <div class="products-slider owl-carousel" data-item="3">
                        @foreach ($products as $product)
                            <div class="item">
                                <div class="image-container">
                                    <a class="item-img-wrapper-link" href="{{ route('market.show', $product->id) }}">
                                        <img class="img-fluid" src="{{ asset($product->pic) }}">
                                    </a>
                                    <div class="item-action-behaviors">
                                        <form action="{{ route('wishlist.store') }}" method="POST"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" value="{{ $product->id }}" name="id">
                                            <input type="hidden" value="{{ $product->name }}" name="name">
                                            <input type="hidden" value="{{ $product->harga_jual }}" name="price">
                                            <input type="hidden" value="1" name="quantity">
                                            <button class="mt-5 mb-1 btn btn-sm" type="submit">Add to Wishlist</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="item-content">
                                    <form action="{{ route('marketcart.store') }}" method="POST"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" value="{{ $product->id }}" name="id">
                                        <input type="hidden" value="{{ $product->name }}" name="name">
                                        <input type="hidden" value="{{ $product->harga_jual }}" name="price">
                                        <input type="hidden" value="1" name="quantity">
                                        <button class="mb-1 button button-outline-secondary" type="submit">Add To
                                            Cart</button>
                                    </form>
                                    <div class="what-product-is">
                                        <ul class="bread-crumb">
                                            <li class="">
                                                <a href="#">{{ $product->category->name }}</a>
                                            </li>
                                        </ul>
                                        <h6 class="item-title">
                                            <a href="{{ route('market.show', $product->id) }}">{{ $product->name }}</a>
                                        </h6>
                                    </div>
                                    <div class="price-template">
                                        <div class="item-new-price">
                                            @currency($product->harga_jual)
                                        </div>
                                    </div>
                                </div>
                                <div class="tag hot">
                                    <span>HOT</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center">
                    <h3 class="sec-maker-h3">Data tidak ditemukan</h3>
                </div>
            @endif
            <!-- Carousel /- -->
        </div>
    </section>
    <!-- Men-Clothing-Timing-Section /- -->
    <!-- Men-Clothing /- -->
    <!-- Continue-Link -->
    <div class="continue-link-wrapper u-s-p-b-80">
        <a class="continue-link" href="#" title="View all products on site">
            <i class="ion ion-ios-more"></i>
        </a>
    </div>
    <!-- Continue-Link /- -->
    @include('layouts.market.slider')
@endsection
