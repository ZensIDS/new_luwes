<x-guest-layout>
    {{-- <h2 class="auth-heading">Selamat datang</h2> --}}
    <p class="auth-brand">LUWES PACITAN</p>

    @if (session('status'))
        <div class="alert-ok">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
        @csrf

        <div class="field {{ $errors->has('loginname') ? 'has-error' : '' }}">
            <label for="loginname">Email / Username</label>
            <div class="input-wrap">
                <i class="fa fa-user lead"></i>
                <input type="text" id="loginname" name="loginname" placeholder="Email atau username"
                       value="{{ old('loginname') }}" autocomplete="username" required autofocus>
            </div>
            @error('loginname')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field {{ $errors->has('password') ? 'has-error' : '' }}">
            <label for="password">Password</label>
            <div class="input-wrap">
                <i class="fa fa-lock lead"></i>
                <input type="password" id="password" name="password" placeholder="Password"
                       autocomplete="current-password" required>
                <button type="button" class="toggle-pw" id="togglePw" aria-label="Tampilkan password">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- <div class="row-opts">
            <label class="check">
                <input type="checkbox" name="remember" checked> Ingat saya
            </label>
        </div> --}}

        <button type="submit" class="btn-login" id="btnLogin">
            <span>Masuk</span> <i class="fa fa-arrow-right"></i>
        </button>
    </form>

    <script>
        (function () {
            var pw = document.getElementById('password');
            var toggle = document.getElementById('togglePw');
            toggle.addEventListener('click', function () {
                var show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                toggle.firstElementChild.className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
            });
            document.getElementById('loginForm').addEventListener('submit', function () {
                var b = document.getElementById('btnLogin');
                b.disabled = true;
                b.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <span>Memproses...</span>';
            });
        })();
    </script>
</x-guest-layout>