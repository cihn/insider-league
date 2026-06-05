<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Insider One Champions League</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar bg-white border-bottom shadow-sm">
        <div class="container">
            <span class="navbar-brand mb-0 h1 d-flex align-items-center gap-2">
                <span class="rounded-circle d-inline-block" style="width:14px;height:14px;background:#ef5b48"></span>
                Insider One <span class="fw-light">Champions League</span>
            </span>
        </div>
    </nav>

    <main id="app" class="container py-4 py-md-5" aria-live="polite">
        <div class="text-center text-muted py-5">Loading&hellip;</div>
    </main>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script type="module" src="{{ asset('js/app.js') }}"></script>
</body>
</html>
