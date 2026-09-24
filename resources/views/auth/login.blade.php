<x-guest-layout>
<div class="login-box-body">
    <p class="login-box-msg">Warehouse Management System</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group has-feedback {{ $errors->has('loginname') ? 'has-error' : '' }}">
            <input type="text" name="loginname" class="form-control"
                   placeholder="Email / Username" value="{{ old('loginname') }}" required autofocus>
            <span class="fa fa-user form-control-feedback"></span>
            @error('loginname')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group has-feedback {{ $errors->has('password') ? 'has-error' : '' }}">
            <input type="password" name="password" class="form-control"
                   placeholder="Password" required autocomplete="current-password">
            <span class="fa fa-lock form-control-feedback"></span>
            @error('password')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>

        <div class="row">
            <div class="col-xs-8">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="remember"> Ingat saya
                    </label>
                </div>
            </div>
            <div class="col-xs-4">
                <button type="submit" class="btn btn-primary btn-block btn-flat">Masuk</button>
            </div>
        </div>
    </form>
</div>
</x-guest-layout>
