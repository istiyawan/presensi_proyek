@extends('layouts.app')

@section('content')
    <x-page-header title="Jabatan" eyebrow="Data bersama"
                   subtitle="Daftar jabatan dipakai di semua proyek dan tampil pada laporan absensi.">
        <x-slot:actions>
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah Jabatan</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover w-100" id="tablePositions">
                <thead><tr><th>Jabatan</th><th class="text-end">Karyawan</th><th class="text-end no-sort" style="width:90px"></th></tr></thead>
                <tbody>
                @foreach ($positions as $p)
                    <tr>
                        <td class="fw-semibold text-ink">{{ $p->name }}</td>
                        <td class="text-end tabular" data-order="{{ $p->employees_count }}"><span class="chip">{{ $p->employees_count }} orang</span></td>
                        <td class="text-end">
                            <button class="btn btn-soft btn-icon btn-sm" data-edit="{{ json_encode($p->only(['id', 'name'])) }}" aria-label="Ubah"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-soft btn-icon btn-sm text-danger" data-delete="{{ route('positions.destroy', $p) }}" data-name="{{ $p->name }}" aria-label="Hapus" @disabled($p->employees_count)><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalPosition" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form class="modal-content" id="formPosition" method="POST" action="{{ route('positions.store') }}" data-ajax-form>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Jabatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nama jabatan</label>
                    <input name="name" class="form-control" placeholder="mis. Ahli Hidrologi">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
