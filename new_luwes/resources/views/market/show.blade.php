@extends('layouts.base')

@section('title', 'Marketplace')

@section('container')
    <!-- Page Introduction Wrapper -->
    <div class="page-style-a">
        <div class="container">
            <div class="page-intro">
                <h2>Detail</h2>
                <ul class="bread-crumb">
                    <li class="has-separator">
                        <i class="ion ion-md-home"></i>
                        <a href="{{ route('market.index') }}">Home</a>
                    </li>
                    <li class="is-marked">
                        <a href="{{ route('market.show', $product->id) }}">Detail</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
<!-- Page Introduction Wrapper /- -->
<!-- Single-Product-Full-Width-Page -->
<div class="page-detail u-s-p-t-80">
    <div class="container">
        <!-- Product-Detail -->
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <!-- Product-zoom-area -->
                <div class="zoom-area">
                    <img id="zoom-pro" class="img-fluid" src="{{ asset($product->pic) }}" data-zoom-image="{{ asset($product->pic) }}" alt="Zoom Image">
                </div>
                <!-- Product-zoom-area /- -->
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <!-- Product-details -->
                <div class="all-information-wrapper">
                    <div class="section-1-title-breadcrumb-rating">
                        <div class="product-title">
                            <h1>
                                <a href="{{ route('market.show', $product->id) }}">{{ $product->name }}</a>
                            </h1>
                        </div>
                    </div>
                    <div class="section-2-short-description u-s-p-y-14">
                        <h6 class="information-heading u-s-m-b-8">Description:</h6>
                        <p>{{ $product->desc }}</p>
                    </div>
                    <div class="section-3-price-original-discount u-s-p-y-14">
                        <div class="price">
                            <h4>@currency($product->harga_jual)</h4>
                        </div>
                    </div>
                    <div class="section-4-sku-information u-s-p-y-14">
                        <h6 class="information-heading u-s-m-b-8">Sku Information:</h6>
                        @if (
                        $product->stocks()
                        // ->where('created_at', '<=', now())
                        // ->where('expired_at', '>=', now())
                        ->sum('qty') > 0
                        )
                            <div class="availability">
                                <span>Availability:</span>
                                <span>In Stock</span>
                            </div>
                            <div class="left">
                                <span>Only:</span>
                                <span>{{ $product->stocks()
                                // ->where('created_at', '<=', now())
                                // ->where('expired_at', '>=', now())
                                ->sum('qty'); }}</span>
                            </div>
                            <div class="left">
                                <span>Weight:</span>
                                <span>{{ $product->berat; }} gram</span>
                            </div>
                        @else
                            <div class="availability">
                                <span>Availability:</span>
                                <span class="text-danger">Out of Stock</span>
                            </div>
                        @endif
                    </div>
                    <div class="section-6-social-media-quantity-actions u-s-p-y-14">
                        <form action="{{ route('marketcart.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="quantity-wrapper u-s-m-b-22">
                                <span>Quantity:</span>
                                <div class="quantity">
                                    <input type="number" class="quantity-text-field" value="1" name="quantity" min="1">
                                </div>
                            </div>
                            <div>
                                <input type="hidden" value="{{ $product->id }}" name="id">
                                <input type="hidden" value="{{ $product->name }}" name="name">
                                <input type="hidden" value="{{ $product->harga_jual }}" name="price">
                                <button class="mb-1 button button-outline-secondary" type="submit">Add To Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Product-details /- -->
            </div>
        </div>
        <!-- Product-Detail /- -->
        <!-- Detail-Tabs -->
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="detail-tabs-wrapper u-s-p-t-80">
                    <div class="detail-nav-wrapper u-s-m-b-30">
                        <ul class="nav single-product-nav justify-content-center">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#specification">Specifications</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#review">Reviews ({{ $product->reviews->count() }})</a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <!-- Specifications-Tab -->
                        <div class="tab-pane fade active show" id="specification">
                            <div class="specification-whole-container">
                                <div class="spec-table u-s-m-b-50">
                                    <h4 class="spec-heading">Product Information</h4>
                                    <table>
                                        <tr>
                                            <td>Warna</td>
                                            <td>{{ $product->warna }}</td>
                                        </tr>
                                        <tr>
                                            <td>Ukuran</td>
                                            <td>{{ $product->ukuran }}</td>
                                        </tr>
                                        <tr>
                                            <td>Berat</td>
                                            <td>{{ $product->berat }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- Specifications-Tab /- -->
                        <!-- Reviews-Tab -->
                        <div class="tab-pane fade" id="review">
                            <div class="review-whole-container">
                                <div class="row r-1 u-s-m-b-26 u-s-p-b-22">
                                    <div class="col-lg-6 col-md-6">
                                        <div class="total-score-wrapper">
                                            <h6 class="review-h6">Average Rating</h6>
                                            <h1>{{ $product->reviews->avg('rating') }}</h1>
                                            <h6 class="review-h6">Based on {{ $product->reviews->count() }} Reviews</h6>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-6">
                                        <div class="total-star-meter">
                                            <div class="star-wrapper">
                                                <span>5 Stars</span>
                                                <div class="star">
                                                    @php
                                                        if ($product->reviews->count() > 0) {
                                                            $percentage = ($fiveStarReviews / $product->reviews->count()) * 100;
                                                            $width = ($percentage / 100) * 75;
                                                        } else {
                                                            $width = 0;
                                                        }
                                                    @endphp
                                                    <span style='width:{{ $width }}px'></span>
                                                </div>
                                                <span>({{ $fiveStarReviews }})</span>
                                            </div>
                                            <div class="star-wrapper">
                                                <span>4 Stars</span>
                                                <div class="star">
                                                    @php
                                                        if ($product->reviews->count() > 0) {
                                                            $percentage = ($fourStarReviews /$product->reviews->count()) * 100;
                                                            $width = ($percentage / 100) * 75;
                                                        } else {
                                                        $width = 0;
                                                        }
                                                    @endphp
                                                    <span style='width:{{$width}}px'></span>
                                                </div>
                                                <span>({{$fourStarReviews}})</span>
                                            </div>
                                            <div class="star-wrapper">
                                                <span>3 Stars</span>
                                                <div class="star">
                                                    @php
                                                        if ($product->reviews->count() > 0) {
                                                        $percentage = ($threeStarReviews /$product->reviews->count()) * 100;
                                                        $width = ($percentage /100) *75;
                                                        } else {
                                                        $width =0;
                                                        }
                                                    @endphp
                                                    <span style='width:{{$width}}px'></span>
                                                </div>
                                                <span>({{$threeStarReviews}})</span>
                                            </div>
                                            <div class="star-wrapper">
                                                <span>2 Stars</span>
                                                <div class="star">
                                                    @php
                                                        if ($product->reviews->count() >0) {
                                                        $percentage =($twoStarReviews /$product->reviews->count()) *100;
                                                        $width =($percentage /100) *75;
                                                        } else {
                                                        $width =0;
                                                        }
                                                    @endphp
                                                    <span style='width:{{$width}}px'></span>
                                                </div>
                                                <span>({{$twoStarReviews}})</span>
                                            </div>
                                            <div class="star-wrapper">
                                                <span>1 Star</span>
                                                <div class="star">
                                                    @php
                                                        if ($product->reviews->count() >0) {
                                                        $percentage =($oneStarReviews /$product->reviews->count()) *100;
                                                        $width =($percentage /100) *75;
                                                        } else {
                                                        $width =0;
                                                        }
                                                    @endphp
                                                    <span style='width:{{$width}}px'></span>
                                                </div>
                                                <span>({{$oneStarReviews}})</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @auth
                                    <div class="row r-2 u-s-m-b-26 u-s-p-b-22">
                                        @if (auth()->user()->reviews()->where('product_id', $product->id))
                                        <div class="col-lg-12">
                                            <ol>
                                                @foreach (auth()->user()->reviews()->where('product_id', $product->id)->get() as $review)
                                                    <li>{{ $review->comment }} By {{ $review->user->name }}</li>
                                                @endforeach
                                            </ol>
                                        </div>
                                        @endif
                                        <hr />
                                        <div class="col-lg-12">
                                            <form action="{{ route('review.store') }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                <div class="your-rating-wrapper">
                                                    <h6 class="review-h6">Your Review is matter.</h6>
                                                    <h6 class="review-h6">Have you used this product before?</h6>
                                                    <div class="star-wrapper u-s-m-b-8">
                                                        <div class="star">
                                                            <span id="your-stars" style='width:0'></span>
                                                        </div>
                                                        <label for="your-rating-value"></label>
                                                        <input id="your-rating-value" name="rating" type="text" class="text-field" placeholder="0.0">
                                                        <span id="star-comment"></span>
                                                    </div>
                                                    <label for="review-text-area">Review
                                                        <span class="astk"> *</span>
                                                    </label>
                                                    <textarea class="text-area u-s-m-b-8" name="comment" id="review-text-area" placeholder="Review"></textarea>
                                                    <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                    <button type="submit" class="button button-outline-secondary">Submit Review</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endauth
                            </div>
                        </div>
                        <!-- Reviews-Tab /- -->
                    </div>
                </div>
            </div>
        </div>
        <!-- Detail-Tabs /- -->
    </div>
</div>
<!-- Single-Product-Full-Width-Page /- -->
@endsection
