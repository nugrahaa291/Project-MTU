<?php

namespace App\Http\Requests;

use App\Models\Lamaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLamaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // portal publik
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

        return [
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

            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:3072'],
            'pas_foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'ktp' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'ijazah' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK ini sudah pernah melamar pada lowongan tersebut.',
            'no_hp.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'tanggal_lahir.before' => 'Usia pelamar minimal 17 tahun.',
            'cv.mimes' => 'CV harus berformat PDF, DOC, atau DOCX.',
            'cv.max' => 'Ukuran CV maksimal 3 MB.',
            'pas_foto.max' => 'Ukuran pas foto maksimal 2 MB.',
            'ktp.max' => 'Ukuran KTP maksimal 2 MB.',
            'ijazah.max' => 'Ukuran ijazah maksimal 3 MB.',
        ];
    }
}
