@extends('layouts.panel')
@section('judul', 'Ganti Password')

@section('content')
    @if ($wajib)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Anda masih memakai <strong>password sementara</strong>. Buat password baru sebelum melanjutkan.
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="card max-w-md space-y-4 p-6">
        @csrf @method('PUT')
        <x-field name="password_lama" label="Password saat ini" type="password" autocomplete="current-password" required />
        <x-field name="password" label="Password baru" type="password" autocomplete="new-password"
                 hint="Minimal 8 karakter, mengandung huruf dan angka." required />
        <x-field name="password_confirmation" label="Ulangi password baru" type="password" autocomplete="new-password" required />
        <button class="btn btn-primary">Simpan Password</button>
    </form>
@endsection
