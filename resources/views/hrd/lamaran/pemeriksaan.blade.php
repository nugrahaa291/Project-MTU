<section class="card space-y-4 p-6">
    <h3 class="font-bold">Pemeriksaan kualifikasi dan berkas</h3>
    @if ($lamaran->lowongan->persyaratan)
        <div><p class="label">Persyaratan {{ $lamaran->lowongan->judul }}</p><p class="whitespace-pre-line text-sm">{{ $lamaran->lowongan->persyaratan }}</p></div>
    @endif
    @php
        $paket = $lamaran->lowongan->paketDokumen();
        $pilihan = config('dokumen_lamaran.pendukung.pilihan');
        $labelStatus = ['belum' => 'Belum diperiksa', 'perbaikan' => 'Perlu perbaikan', 'valid' => 'Valid'];
    @endphp
    @if ($paket['pendukung'] ?? false)
        <p class="text-sm">Satu berkas pendukung wajib valid ({{ implode(' / ', array_map(fn ($k) => \App\Models\Lamaran::DOKUMEN[$k], $pilihan)) }}).</p>
    @endif
    <form method="POST" action="{{ route($rute.'.lamaran.proses', $lamaran) }}" class="space-y-3">
        @csrf
        @foreach ($lamaran->berkasTampil() as $kolom => $label)
            <div class="grid items-center gap-2 border-b border-line pb-2 text-sm sm:grid-cols-2">
                <div>
                    @if ($lamaran->{$kolom})
                        <a href="{{ route($rute.'.lamaran.dokumen', [$lamaran, $kolom]) }}" target="_blank" rel="noopener" class="font-semibold text-brand-dark underline">{{ $label }} &nearr;</a>
                    @else
                        <span>{{ $label }} · Belum diunggah</span>
                    @endif
                    <span class="block text-xs text-gray-500">{{ ($paket[$kolom] ?? false) ? 'Wajib' : (isset($paket['pendukung']) && in_array($kolom, $pilihan, true) ? 'Pilihan berkas pendukung' : 'Opsional') }}</span>
                </div>
                @if ($bisaPeriksa && $lamaran->{$kolom})
                    <select aria-label="Verifikasi {{ $label }}" class="input" name="verifikasi[{{ $kolom }}]">
                        @foreach ($labelStatus as $nilai => $teks)
                            <option value="{{ $nilai }}" @selected(old("verifikasi.$kolom", $lamaran->verifikasi_berkas[$kolom] ?? 'belum') === $nilai)>{{ $teks }}</option>
                        @endforeach
                    </select>
                @else
                    <span>{{ ! $bisaPeriksa && $lamaran->verifikasi_berkas === null ? 'Tidak ada catatan verifikasi' : $labelStatus[$lamaran->verifikasi_berkas[$kolom] ?? 'belum'] }}</span>
                @endif
            </div>
        @endforeach
        @if ($bisaPeriksa)
            @if ($status === \App\Enums\StatusLamaran::Seleksi)
                <p class="text-sm text-gray-600">Lengkapi pemeriksaan lamaran lama ini agar dapat masuk daftar interview.</p>
            @endif
            <x-field name="alasan_seleksi" label="Alasan kelayakan untuk interview" as="textarea" :value="$lamaran->alasan_seleksi" maxlength="1000" hint="Sebutkan kualifikasi yang sesuai dengan persyaratan lowongan dan berkas yang mendukungnya." required />
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline" formaction="{{ route($rute.'.lamaran.berkas', $lamaran) }}" formnovalidate>Simpan pemeriksaan</button>
                <button type="submit" class="btn btn-primary">Lanjut ke Interview</button>
            </div>
        @elseif ($lamaran->alasan_seleksi || $lamaran->lolos_berkas_pada)
            <p class="label">Catatan kualifikasi dan berkas</p>
            <p class="whitespace-pre-line text-sm">{{ $lamaran->alasan_seleksi }}</p>
            <p class="text-xs text-gray-500">{{ $lamaran->pemeriksa?->name }} {{ $lamaran->lolos_berkas_pada?->format('d/m/Y H:i') }}</p>
        @endif
    </form>
</section>
