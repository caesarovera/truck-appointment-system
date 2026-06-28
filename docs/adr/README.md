# Architecture Decision Records (ADR)

> **Apa ini:** catatan keputusan arsitektur — satu file per keputusan. Menyimpan **alasan**
> ("kenapa dulu diputuskan begini"), bukan aturan. Pelengkap `CLAUDE.md` (aturan) dan
> `docs/ARCHITECTURE.md` (gambaran besar).
>
> **Kenapa ada:** mencegah *architecture drift* di proyek multi-sesi. Tanpa ADR, alasan
> sebuah keputusan hilang seiring waktu → orang mengubahnya tanpa tahu konsekuensinya, atau
> berdebat ulang hal yang sudah diputuskan.

## Format

Tiap ADR memakai struktur ringan: **Status · Context · Decision · Consequences**
(plus *Kapan ditinjau ulang* bila relevan). Sekali ditulis, ADR **tidak diedit ulang** —
kalau keputusan berubah, buat ADR baru yang men-*supersede* yang lama (tandai statusnya).

Status yang dipakai: `Accepted` · `Superseded by ADR-XXXX` · `Deprecated`.

## Daftar

| # | Judul | Status |
|---|-------|--------|
| [0001](0001-package-by-layer.md) | Organisasi folder package-by-layer (bukan package-by-feature) | Accepted |
| [0002](0002-repository-interface.md) | Akses data lewat Repository ber-interface (Ports & Adapters) | Accepted |
| [0003](0003-defer-token-abilities.md) | Tunda penegakan token abilities Sanctum | Accepted |

## Cara menambah ADR
1. Salin format di salah satu file di atas, nomori berikutnya (`000N-judul-kebab.md`).
2. Tulis Context (situasi & gaya tarik-menarik), Decision (yang dipilih), Consequences
   (untung/rugi/risiko).
3. Tambahkan satu baris ke tabel **Daftar** di atas.
4. Bila menggantikan ADR lama, ubah status ADR lama jadi `Superseded by ADR-000N`.
