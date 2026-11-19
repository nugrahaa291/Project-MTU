<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('pwa/fonts/poppins.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas px-5">
    <div class="w-full max-w-sm">
        <a href="{{ route('lowongan.index') }}" class="mb-6 block text-4xl font-bold tracking-tighter text-ink">
            MTU<span class="text-brand">.</span>
        </a>

        <div class="card p-6">
            <h1 class="mb-1 text-lg font-bold">Masuk</h1>
            <p class="mb-5 text-sm text-gray-500">Khusus HRD / admin.</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-field name="email" label="Email" type="email" autocomplete="username" autofocus required />
                <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ingat" value="1" class="rounded border-gray-300 text-brand focus:ring-brand"> Ingat saya
                </label>
                <button class="btn btn-primary w-full">Masuk</button>
            </form>
        </div>

        <p class="mt-4 text-center text-sm text-gray-600">
            Karyawan? <a href="{{ route('pwa') }}" class="font-semibold text-brand">Masuk ke halaman Absen</a> dengan NIP dan PIN.<br>
            Mencari lowongan? <a href="{{ route('lowongan.index') }}" class="font-semibold text-brand">Lihat di sini</a>
        </p>
    </div>
</body>
</html>
