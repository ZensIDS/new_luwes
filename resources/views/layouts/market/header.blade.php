<!-- Header -->
<header>
    <!-- Top-Header -->
    <div class="full-layer-outer-header">
        <div class="container clearfix">
            <nav>
                <ul class="primary-nav g-nav">
                    <li>
                        <a href="tel:{{ json_decode(Storage::disk('public')->get('settings.json'), true)['telp'] }}">
                            <i class="fas fa-phone u-c-brand u-s-m-r-9"></i>
                            Telephone: {{ json_decode(Storage::disk('public')->get('settings.json'), true)['telp'] }}
                        </a>
                    </li>
                    <li>
                        <a href="mailto:{{ json_decode(Storage::disk('public')->get('settings.json'), true)['email'] }}">
                            <i class="fas fa-envelope u-c-brand u-s-m-r-9"></i>
                            E-mail: {{ json_decode(Storage::disk('public')->get('settings.json'), true)['email'] }}
                        </a>
                    </li>
                </ul>
            </nav>
            <nav>
                @auth
                <ul class="secondary-nav g-nav">
                    <li>
                        <a>My Account
                            <i class="fas fa-chevron-down u-s-m-l-9"></i>
                        </a>
                        <ul class="g-dropdown" style="width:200px">
                            <li><a href="{{ route('marketcart.index') }}"><i class="fas fa-cog u-s-m-r-9"></i>My Cart</a></li>
                            <li><a href="{{ route('wishlist.index') }}"><i class="far fa-heart u-s-m-r-9"></i>My Wishlist</a></li>
                            <li><a href="{{ route('market.checkout') }}"><i class="far fa-check-circle u-s-m-r-9"></i>Checkout</a></li>
                            <li><a href="{{ url('profile') }}"><i class="fas fa-user u-s-m-r-9"></i>Profile</a></li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="btn btn-default btn-flat">Logout</button>
                            </form>
                        </ul>
                    </li>
                </ul>
                @else
                <ul class="secondary-nav g-nav">
                    <li><a href="{{ route('login') }}"><i class="fas fa-sign-in-alt u-s-m-r-9"></i>Login / Signup</a></li>
                </ul>
                @endauth
            </nav>
        </div>
    </div>
    <!-- Top-Header /- -->
    <!-- Mid-Header -->
    <div class="full-layer-mid-header">
        <div class="container">
            <div class="clearfix row align-items-center">
                <div class="col-lg-3 col-md-9 col-sm-6">
                    {{-- <div class="brand-logo text-lg-center"> --}}
                        {{-- <a href="{{ route('market.index') }}"> --}}
                            {{-- <img src="{{ asset('assets/marketplace/images/main-logo/groover-branding-1.png') }}" --}}
                                {{-- alt="Groover Brand Logo" class="app-brand-logo"> --}}
                        {{-- </a> --}}
                    {{-- </div> --}}
                    <div class="v-menu v-close">
                        <span class="v-title">
                            <i class="ion ion-md-menu"></i>
                            All Categories
                            <i class="fas fa-angle-down"></i>
                        </span>
                        <nav>
                            <div class="v-wrapper">
                                <ul class="v-list animated fadeIn">
                                    @foreach($categories as $category)
                                        <li>
                                            <a href="{{ route('market.index', ['category' => Str::slug($category->name, '_')]) }}">
                                                <i class="ion ion-md-phone-portrait"></i>{{$category->name}}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-6 u-d-none-lg">
                    <form action="{{ route('market.index') }}" method="GET" class="form-searchbox">
                        <label class="sr-only" for="search-landscape">Search</label>
                        <input id="search-landscape" name="search" type="text" class="text-field" placeholder="Search everything">
                        <button id="btn-search" type="submit" class="button button-primary fas fa-search"></button>
                    </form>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6">
                    <nav>
                        <ul class="mid-nav g-nav">
                            <li class="u-d-none-lg">
                                <a href="{{ route('market.index') }}">
                                    <i class="ion ion-md-home u-c-brand"></i>
                                </a>
                            </li>
                            <li class="u-d-none-lg">
                                <a href="{{ route('wishlist.index') }}">
                                    <span class="item-counter">{{ app('wishlist')->getContent()->count() }}</span>
                                    <i class="far fa-heart"></i>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('marketcart.index') }}">
                                    <i class="ion ion-md-basket"></i>
                                    @auth
                                        <span class="item-counter">{{ Cart::session(auth()->id())->getContent()->count() }}</span>
                                        <span class="item-price">Rp. {{ number_format(Cart::session(auth()->id())->getTotal(), 0, ',', '.') }}</span>
                                    @endauth
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <!-- Mid-Header /- -->
    <!-- Responsive-Buttons -->
    <div class="fixed-responsive-container">
        <div class="fixed-responsive-wrapper">
            <button type="button" class="button fas fa-search" id="responsive-search"></button>
        </div>
        <div class="fixed-responsive-wrapper">
            <a href="{{ route('wishlist.index') }}">
                <i class="far fa-heart"></i>
                <span class="fixed-item-counter">{{ app('wishlist')->getContent()->count() }}</span>
            </a>
        </div>
    </div>
    <!-- Responsive-Buttons /- -->
    <!-- Bottom-Header -->
    {{-- <div class="full-layer-bottom-header"> --}}
        {{-- <div class="container"> --}}
            {{-- <div class="row align-items-center"> --}}
                {{-- <div class="col-lg-3"> --}}
                    {{-- <div class="v-menu v-close"> --}}
                        {{-- <span class="v-title"> --}}
                            {{-- <i class="ion ion-md-menu"></i> --}}
                            {{-- All Categories --}}
                            {{-- <i class="fas fa-angle-down"></i> --}}
                        {{-- </span> --}}
                        {{-- <nav> --}}
                            {{-- <div class="v-wrapper"> --}}
                                {{-- <ul class="v-list animated fadeIn"> --}}
                                    {{-- @foreach($categories as $category) --}}
                                        {{-- <li> --}}
                                            {{-- <a href="{{ route('market.index', ['category' => Str::slug($category->name, '_')]) }}"> --}}
                                                {{-- <i class="ion ion-md-phone-portrait"></i>{{$category->name}} --}}
                                            {{-- </a> --}}
                                        {{-- </li> --}}
                                    {{-- @endforeach --}}
                                {{-- </ul> --}}
                            {{-- </div> --}}
                        {{-- </nav> --}}
                    {{-- </div> --}}
                {{-- </div> --}}
                {{-- <div class="col-lg-9"> --}}
                    {{-- <ul class="bottom-nav g-nav u-d-none-lg"> --}}
                        {{-- <li><a href="#">New Arrivals<span class="superscript-label-new">NEW</span></a></li> --}}
                        {{-- <li><a href="#">Exclusive Deals<span class="superscript-label-hot">HOT</span></a></li> --}}
                        {{-- <li><a href="#">Flash Deals</a></li> --}}
                        {{-- <li><a href="#">Super Sale<span class="superscript-label-discount">-15%</span></a></li> --}}
                    {{-- </ul> --}}
                {{-- </div> --}}
            {{-- </div> --}}
        {{-- </div> --}}
    {{-- </div> --}}
    <!-- Bottom-Header /- -->
</header>
<!-- Header /- -->
