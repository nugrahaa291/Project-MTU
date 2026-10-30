<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-ink px-4">
    <div class="w-full max-w-sm">
        <a href="{{ route('lowongan.index') }}" class="mb-6 block text-center text-3xl font-bold text-white">
            {{ config('app.name') }}<span class="text-brand">.</span>
        </a>

        <div class="card p-6">
            <h1 class="mb-1 text-lg font-bold">Masuk</h1>
            <p class="mb-5 text-sm text-gray-500">Khusus HRD dan karyawan.</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-field name="email" label="Email" type="email" autocomplete="username" autofocus required />
                <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ingat" value="1" class="rounded border-gray-300 text-brand focus:ring-brand"> Ingat saya
                </label>
                <button class="btn btn-primary w-full">Masuk Donk</button>
            </form>
        </div>

        <p class="mt-4 text-center text-sm text-gray-400">
            Mencari lowongan? <a href="{{ route('lowongan.index') }}" class="font-semibold text-brand">Lihat di sini</a>
        </p>
    </div>
</body>
</html>
