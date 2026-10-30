<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Absensi - {{ config('app.name') }}</title>
    <meta name="theme-color" content="#30323a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Absensi">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/absen/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/pwa/icons/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/pwa/style.css?v={{ $versi }}">
</head>
<body>
    <div id="app">
        <noscript><p style="padding:24px">Aplikasi absensi membutuhkan JavaScript.</p></noscript>
    </div>
    <div id="toast" aria-live="polite"></div>
    <script type="module" src="/pwa/app.js?v={{ $versi }}"></script>
</body>
</html>
