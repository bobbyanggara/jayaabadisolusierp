@extends('jaspest.blog.admin.layout')
@section('content')
    <div class="admin-title"><div><a class="admin-back" href="{{ route('jaspest.blog.admin.index') }}"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6 6-6-6 6-6"/></svg><span>Kembali ke daftar blog</span></a><h1>{{ $post->exists ? 'Edit blog' : 'Tulis blog' }}</h1></div></div>
    <form class="admin-form" method="post" action="{{ $post->exists ? route('jaspest.blog.admin.update', $post) : route('jaspest.blog.admin.store') }}" enctype="multipart/form-data">
        @csrf
        @if($post->exists) @method('PUT') @endif
        <div class="admin-editor">
            <section class="admin-card"><h2>Isi tulisan</h2>
                <label for="blog-title">Judul <span>*</span></label><input id="blog-title" name="title" value="{{ old('title', $post->title) }}" maxlength="160" required>
                <label for="blog-slug">Alamat URL (slug) <span>*</span></label><input id="blog-slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" required><small>Gunakan huruf kecil, angka, dan tanda hubung. Contoh: persiapan-konsultasi. Pertahankan URL tulisan yang sudah terbit agar tautan lama tetap berfungsi.</small>
                <div class="admin-two-fields"><div><label for="blog-category">Kategori <span>*</span></label><input id="blog-category" name="category" value="{{ old('category', $post->category) }}" maxlength="60" required></div><div><label for="blog-author">Nama penulis <span>*</span></label><input id="blog-author" name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="100" required></div></div>
                <label for="blog-excerpt">Ringkasan <span>*</span></label><textarea id="blog-excerpt" name="excerpt" maxlength="300" rows="3" required>{{ old('excerpt', $post->excerpt) }}</textarea><small>Maksimal 300 karakter. Ditampilkan pada kartu blog.</small>
                <label for="blog-body">Isi blog <span>*</span></label><textarea id="blog-body" class="admin-body" name="body" minlength="40" maxlength="60000" rows="19" required>{{ old('body', $post->body) }}</textarea><small>Pisahkan paragraf dengan satu baris kosong. Tulis sebagai teks biasa.</small>
            </section>
            <section class="admin-card"><h2>Gambar utama</h2>@if($post->cover_path)<img class="admin-cover" src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $post->title }}">@endif
                <label for="blog-cover">{{ $post->cover_path ? 'Ganti gambar' : 'Unggah gambar' }}</label><input id="blog-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, atau WebP. Maksimal 5 MB. Tanpa unggahan baru, gambar saat ini tetap dipakai.</small>
                <label for="blog-cover-alt">Deskripsi gambar</label><input id="blog-cover-alt" name="cover_alt" value="{{ old('cover_alt', $post->cover_alt) }}" maxlength="180"><small>Jelaskan isi gambar untuk pembaca yang memakai pembaca layar.</small>
            </section>
        </div>
        <div class="admin-settings">
            <section class="admin-card"><h2>Publikasi</h2><label for="blog-status">Status <span>*</span></label><select id="blog-status" name="status" required><option value="draft" @selected(old('status', $post->status) === 'draft')>Draf</option><option value="published" @selected(old('status', $post->status) === 'published')>Terbit</option></select><small>Draf hanya terlihat oleh admin.</small>
                <label for="blog-published-at">Tanggal dan waktu terbit</label><input id="blog-published-at" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"><small>Zona waktu {{ config('app.timezone') }}. Kosongkan untuk terbit sekarang. Tanggal mendatang menjadwalkan publikasi.</small>
                <button class="button button-blue button-small" type="submit"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan blog</span></button><p class="admin-save-note">Pilih status Draf untuk menarik tulisan dari halaman publik.</p>
            </section>
            <section class="admin-card"><h2>Tampilan di mesin pencari</h2><label for="blog-meta-title">Judul SEO</label><input id="blog-meta-title" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" maxlength="100"><small>Opsional. Jika kosong, menggunakan judul tulisan.</small><label for="blog-meta-description">Deskripsi SEO</label><textarea id="blog-meta-description" name="meta_description" maxlength="170" rows="4">{{ old('meta_description', $post->meta_description) }}</textarea><small>Maksimal 170 karakter. Jika kosong, menggunakan ringkasan.</small></section>
        </div>
    </form>
@endsection
