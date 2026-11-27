<?php

namespace App\Http\Requests;

use App\Models\Lamaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLamaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('pelamar') !== null;
    }

    /** Bersihkan spasi/strip di NIK & nomor HP sebelum divalidasi. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => preg_replace('/\D/', '', (string) $this->nik),
            'no_hp' => preg_replace('/[\s\-.]/', '', (string) $this->no_hp),
            'email' => strtolower(trim((string) $this->email)),
        ]);
    }

    public function rules(): array
    {
        $lowongan = $this->route('lowongan');

        $aturan = [
            // Tahap 1: data diri
            // Satu NIK hanya boleh melamar satu kali pada lowongan yang sama
            'nik' => ['required', 'digits:16', Rule::unique('lamaran', 'nik')->where('lowongan_id', $lowongan->id)],
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'no_hp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:-17 years'],
            'alamat' => ['required', 'string', 'max:500'],
            'pendidikan' => ['required', Rule::in(Lamaran::PENDIDIKAN)],
        ];

        // Tahap 2: dokumen, sesuai paket lowongan (wajib / opsional)
        foreach ($lowongan->paketDokumen() as $kunci => $wajib) {
            if ($kunci === 'pendukung') {
                $p = config('dokumen_lamaran.pendukung');
                $aturan['jenis_pendukung'] = $wajib
                    ? ['required', Rule::in($p['pilihan'])]
                    : ['nullable', 'required_with:dokumen_pendukung', Rule::in($p['pilihan'])];
                $aturan['dokumen_pendukung'] = $wajib
                    ? ['required', ...$this->aturanBerkas($p)]
                    : ['nullable', 'required_with:jenis_pendukung', ...$this->aturanBerkas($p)];

                continue;
            }

            $aturan[$kunci] = [
                $wajib ? 'required' : 'nullable',
                ...$this->aturanBerkas(config("dokumen_lamaran.berkas.{$kunci}")),
            ];
        }

        return $aturan;
    }

    /** Aturan unggah standar dari config: file, (gambar), format, ukuran. */
    private function aturanBerkas(array $b): array
    {
        $aturan = ['file'];
        if (! empty($b['gambar'])) {
            $aturan[] = 'image';
        }

        return [...$aturan, 'mimes:'.implode(',', $b['mimes']), 'max:'.$b['max']];
    }

    public function attributes(): array
    {
        return [
            ...Lamaran::DOKUMEN,
            'jenis_pendukung' => 'Jenis dokumen pendukung',
            'dokumen_pendukung' => 'Dokumen pendukung',
        ];
    }

    public function messages(): array
    {
        $pesan = [
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK ini sudah pernah melamar pada lowongan tersebut.',
            'no_hp.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'tanggal_lahir.before' => 'Usia pelamar minimal 17 tahun.',
            'jenis_pendukung.required' => 'Pilih salah satu: ijazah, akte lahir, atau buku nikah.',
            'jenis_pendukung.in' => 'Pilihan dokumen pendukung tidak valid.',
            'dokumen_pendukung.required' => 'Unggah dokumen pendukung yang Anda pilih.',
        ];

        $semua = config('dokumen_lamaran.berkas') + ['dokumen_pendukung' => config('dokumen_lamaran.pendukung')];

        foreach ($semua as $kunci => $b) {
            $label = Lamaran::DOKUMEN[$kunci] ?? 'Dokumen pendukung';
            $pesan["{$kunci}.mimes"] = "{$label} harus berformat ".strtoupper(implode('/', $b['mimes'])).'.';
            $pesan["{$kunci}.max"] = "Ukuran {$label} maksimal ".round($b['max'] / 1024).' MB.';
        }

        return $pesan;
    }
}