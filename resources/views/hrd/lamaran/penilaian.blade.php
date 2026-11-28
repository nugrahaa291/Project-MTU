<section id="interview" class="card space-y-3 p-6">
    <h3 class="font-bold">Hasil Interview</h3>
    @if ($lamaran->dinilai_pada)
        <p class="text-sm">{{ $lamaran->tanggal_interview->format('d/m/Y') }} · Pewawancara {{ $lamaran->pewawancara }}</p>
        <p class="whitespace-pre-line">{{ $lamaran->hasil_interview }}</p>
        <p class="font-semibold">Rekomendasi · {{ $lamaran->rekomendasi_interview === 'lanjut' ? 'Lanjut' : 'Tidak lanjut' }}</p>
        <p class="text-xs text-gray-500">Dicatat oleh {{ $lamaran->penilai?->name ?? 'Petugas' }} · {{ $lamaran->dinilai_pada->format('d/m/Y H:i') }}</p>
    @else
        <form method="POST" action="{{ route($rute.'.interview.store', $lamaran) }}" class="space-y-3" data-konfirmasi="Simpan penilaian interview ini sebagai dasar keputusan akhir?">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="tanggal_interview" label="Tanggal interview" type="date" :min="$lamaran->created_at->toDateString()" :max="today()->toDateString()" required />
                <x-field name="pewawancara" label="Nama pewawancara" :value="auth()->user()->name" maxlength="150" required />
            </div>
            <x-field name="hasil_interview" label="Penilaian untuk posisi {{ $lamaran->lowongan->judul }}" as="textarea" maxlength="2000" :hint="in_array($lamaran->lowongan->paket_dokumen, config('dokumen_lamaran.unit'), true)
                    ? 'Catat contoh jawaban tentang pengalaman membawa unit, pemeriksaan kendaraan, atau penanganan gangguan saat berkendara.'
                    : 'Catat kompetensi yang diuji sesuai persyaratan lowongan dan contoh jawaban atau pengalaman yang mendukung penilaian.'" required />
            <x-field name="rekomendasi_interview" label="Rekomendasi pewawancara" as="select" required>
                <option value="">Pilih rekomendasi</option>
                <option value="lanjut" @selected(old('rekomendasi_interview') === 'lanjut')>Lanjut</option>
                <option value="tidak_lanjut" @selected(old('rekomendasi_interview') === 'tidak_lanjut')>Tidak lanjut</option>
            </x-field>
            <button class="btn btn-primary">Simpan hasil interview</button>
        </form>
    @endif
</section>
