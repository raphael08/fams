<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Document Archive</title>
        <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'dark' && t !== 'light') { t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex align-items-center" style="min-height:100vh">
<div class="container" style="max-width:400px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h4 class="mb-1">Document Archive</h4>
        <p class="text-muted">Sign in to continue</p>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Username or email</label>
                <input name="login" class="form-control @error('login') is-invalid @enderror" value="{{ old('login') }}" autofocus required>
                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="remember" id="r"><label for="r" class="form-check-label">Remember me</label></div>
            <button class="btn btn-primary w-100">Sign in</button>
        </form>
    </div></div>
</div>
</body>
</html>
