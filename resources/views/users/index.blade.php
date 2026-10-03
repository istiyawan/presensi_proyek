@extends('layouts.app')

@section('content')
    <x-page-header title="Pengguna Admin" eyebrow="Akses web admin"
                   subtitle="Super admin mengelola semua proyek; admin proyek hanya proyek yang ditugaskan. Akun karyawan &amp; Team Leader dikelola di menu Karyawan.">
        <x-slot:actions>
            <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg me-1"></i>Tambah Admin</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover w-100" id="tableUsers">
                <thead><tr><th>Pengguna</th><th>Peran</th><th>Proyek</th><th>Login terakhir</th><th>Status</th><th class="text-end"></th></tr></thead>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="modalUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formUser" method="POST" action="{{ route('users.store') }}" data-ajax-form autocomplete="off">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Pengguna Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama</label>
                            <input name="name" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Username</label>
                            <input name="username" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" placeholder="opsional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <input name="password" type="password" class="form-control" autocomplete="new-password" placeholder="min. 8 karakter">
                            <div class="form-text edit-only">Kosongkan bila tidak diubah.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Peran</label>
                            <div class="row g-2">
                                @foreach (['project_admin' => ['Admin proyek', 'Kelola proyek tertentu', 'building'], 'super_admin' => ['Super admin', 'Akses penuh semua proyek', 'shield-lock']] as $value => [$label, $desc, $icon])
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="role" value="{{ $value }}" id="role-{{ $value }}">
                                        <label class="btn btn-soft w-100 text-start p-3 h-100" for="role-{{ $value }}">
                                            <i class="bi bi-{{ $icon }} text-brand d-block mb-1 fs-5"></i>
                                            <span class="fw-bold d-block">{{ $label }}</span>
                                            <span class="fs-8 text-muted fw-normal">{{ $desc }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if ($multiProject)
                            <div class="col-12" id="projectField">
                                <label class="form-label">Proyek yang dikelola</label>
                                <div class="select2-wrap">
                                    <select name="project_ids[]" class="form-select select2" multiple data-placeholder="Pilih proyek">
                                        @foreach ($projects as $p)
                                            <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="swUserActive" data-bool checked>
                                <label class="form-check-label fw-semibold" for="swUserActive">Akun aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
