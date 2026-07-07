# docs/GIT-HISTORY-REWRITE.md — Panduan Menulis Ulang Pesan Commit di Seluruh History

> Panduan **manual langkah-demi-langkah** (tanpa AI) menulis ulang bagian pesan commit
> di seluruh history git — kasus nyata di repo ini (2026-07-08): mengganti trailer
> `Co-Authored-By: Claude ... <noreply@anthropic.com>` menjadi
> `Co-Authored-By: Overa Caesar <caesarovera@gmail.com>` pada **24 commit**, termasuk
> 17 commit yang sudah ter-push ke GitHub.
>
> Posisi dokumen: *how-to operasional* seperti `SETUP-GUIDE.md`. Setiap langkah diberi
> **kenapa**-nya supaya bisa diulang dengan paham, bukan sekadar copy-paste.

---

## 0. Pahami dulu konsekuensinya (WAJIB dibaca sebelum mulai)

Menulis ulang pesan commit = membuat **objek commit baru** untuk setiap commit yang
diubah. Karena SHA commit dihitung dari seluruh isinya (termasuk pesan dan SHA parent),
maka:

1. **SHA SEMUA commit berubah** sejak commit pertama yang diubah — kalau commit paling
   awal ikut diubah (kasus kita), *seluruh* history dapat SHA baru.
2. History yang sudah ter-push **harus di-force-push** — remote akan ditimpa.
3. **Clone lain jadi "yatim"**: perangkat/rekan yang punya clone lama tidak boleh
   `git pull` (akan menghasilkan merge kacau antara history lama & baru). Mereka harus:
   ```bash
   git fetch origin
   git reset --hard origin/main   # buang history lama lokal, ikuti yang baru
   ```
4. Referensi SHA lama di luar git (di PR, issue, dokumen, chat) menjadi mati.

**Kapan aman:** repo solo / semua kolaborator tahu & siap reset.
**Kapan JANGAN:** repo dengan kolaborator aktif yang sedang punya branch berjalan di
atas history lama — koordinasikan dulu atau jangan lakukan.

---

## 1. Audit dulu: seberapa besar dampaknya?

Sebelum menyentuh apa pun, ukur cakupan. Tiga pertanyaan yang harus terjawab:
**berapa commit terdampak · berapa total commit · berapa yang sudah ter-push**.

```bash
# 1) Commit mana saja yang memuat teks yang mau diganti?
git log --format="%h %s" --grep="Co-Authored-By: Claude"

# 2) Total commit di branch ini
git rev-list --count HEAD

# 3) Berapa commit yang BELUM ter-push? (0 = semuanya sudah di remote)
git log origin/main..HEAD --oneline | wc -l

# 4) Remote-nya ke mana?
git remote -v
```

> **Kenapa:** kalau semua commit terdampak masih *unpushed*, tidak perlu force-push
> sama sekali (risiko jauh lebih kecil). Kasus kita: 24/24 commit terdampak,
> 17 sudah ter-push → force-push tak terhindarkan, jadi putuskan sadar sejak awal.

---

## 2. Amankan pekerjaan yang belum ter-commit

`git filter-branch` **menolak jalan** bila working tree kotor:

```
Cannot rewrite branches: You have unstaged changes.
```

(Kami benar-benar menabrak error ini.) Simpan dulu semua WIP — termasuk file
untracked (`-u`):

```bash
git stash push -u -m "wip sebelum rewrite"
```

> **Kenapa stash, bukan commit:** WIP-nya belum siap jadi commit; stash mengembalikannya
> persis seperti semula setelah rewrite selesai (`git stash pop` di langkah 5).

**(Opsional tapi disarankan) backup eksplisit** sebelum rewrite:

```bash
git branch backup-pre-rewrite    # penunjuk ke history lama, mudah kembali
```

`filter-branch` sebenarnya membuat backup otomatis di `refs/original/`, tapi branch
backup yang kamu buat sendiri lebih jelas dan tidak ikut terhapus oleh cleanup.

---

## 3. Jalankan rewrite

```bash
FILTER_BRANCH_SQUELCH_WARNING=1 \
git filter-branch -f \
  --msg-filter "sed -e 's|Co-Authored-By: Claude.*|Co-Authored-By: Overa Caesar <caesarovera@gmail.com>|'" \
  -- --all
```

Bedah tiap bagian:

| Bagian | Artinya | Kenapa |
|---|---|---|
| `FILTER_BRANCH_SQUELCH_WARNING=1` | bungkam peringatan "filter-branch punya banyak jebakan, pertimbangkan filter-repo" | kita sudah tahu risikonya; untuk repo kecil filter-branch cukup & bawaan git (tanpa install apa pun) |
| `-f` | timpa backup `refs/original/` dari percobaan sebelumnya | tanpa ini, run kedua gagal "previous backup exists" |
| `--msg-filter '<cmd>'` | tiap pesan commit dialirkan lewat stdin → `<cmd>` → stdout jadi pesan baru | ini satu-satunya bagian history yang ingin kita ubah; isi file & author tidak disentuh |
| `sed 's|pola|ganti|'` | ganti baris yang cocok pola | `Co-Authored-By: Claude.*` menangkap SEMUA varian (Claude Fable 5, Claude Opus 4.8, dst.) — commit lama bisa punya varian nama model berbeda; pakai `|` sebagai pemisah sed supaya tidak bentrok dengan `/` di URL/email |
| `-- --all` | proses semua ref (branch lokal + remote-tracking + stash) | kalau hanya `HEAD`, branch lain masih menunjuk history lama |

Keluaran sukses terlihat seperti:

```
Ref 'refs/heads/main' was rewritten
Ref 'refs/remotes/origin/main' was rewritten
Ref 'refs/stash' was rewritten
```

> **Catatan:** `refs/remotes/origin/main` (catatan LOKAL tentang posisi remote) ikut
> ditulis ulang. Ini menimbulkan jebakan di langkah 7 — jangan lewati bagian itu.

---

## 4–5. Kembalikan WIP & verifikasi hasil

```bash
git stash pop
```

Lalu verifikasi **lewat `git log`, bukan lewat SHA lama**:

```bash
# Semua trailer baru ada? (harus = jumlah commit terdampak, kasus kita 24)
git log --format=%B | grep -c "Co-Authored-By: Overa Caesar"

# Tidak ada sisa teks lama? (harus 0)
git log --format=%B | grep -c "Co-Authored-By: Claude"
```

> **Jebakan yang kami alami:** meng-query SHA *lama* (`git log -1 <sha-lama>`) masih
> menampilkan **pesan lama** — objek lama belum dihapus, hanya "menggantung"
> (*dangling*) dan masih resolvable. Itu BUKAN tanda rewrite gagal. Sumber kebenaran
> adalah `git log` (history aktif), bukan SHA lama.

---

## 6. Bersihkan backup refs

`filter-branch` meninggalkan penunjuk ke history lama di `refs/original/`:

```bash
git update-ref -d refs/original/refs/heads/main
git update-ref -d refs/original/refs/remotes/origin/main
```

> **Kenapa:** selama refs ini ada, history lama tidak akan pernah di-garbage-collect
> dan tool seperti `gitk`/`git log --all` menampilkan dua versi history yang
> membingungkan.

**(Opsional) benar-benar membuang objek lama dari disk** — hanya bila perlu (mis.
teks lama mengandung rahasia). Ini juga menghapus jaring pengaman reflog, jadi
lakukan paling akhir setelah yakin semuanya benar:

```bash
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

---

## 7. Push ke remote — jebakan `--force-with-lease`

`--force-with-lease` lebih aman daripada `--force` polos: ia hanya menimpa remote
bila posisi remote masih sama dengan yang terakhir kita ketahui (mencegah menimpa
push orang lain yang belum kita fetch).

**TAPI** ada jebakan pasca-filter-branch: "yang terakhir kita ketahui" dibaca dari
`refs/remotes/origin/main` — yang barusan **ikut ditulis ulang** di langkah 3. Nilai
lease-nya kini salah (menunjuk SHA baru, padahal remote masih SHA lama) → push akan
ditolak. Solusinya: **fetch dulu** supaya tracking ref kembali mencerminkan keadaan
remote yang sebenarnya, baru push:

```bash
git fetch origin                          # tracking ref kembali ke posisi remote nyata
git push --force-with-lease origin main   # timpa remote dengan history baru
```

Keluaran sukses:

```
 + f977f55...d5a3929 main -> main (forced update)
```

Tanda `+` = *forced update* (bukan fast-forward) — memang itu yang diharapkan.

---

## 8. Checklist pasca-rewrite

- [ ] `git log --format=%B | grep -c "<teks lama>"` → **0**.
- [ ] `git status --short --branch` → `## main...origin/main` tanpa ahead/behind.
- [ ] Kabari semua pemilik clone lain: `git fetch && git reset --hard origin/main`
      (JANGAN `git pull`).
- [ ] Commit **berikutnya** memakai trailer baru — kalau tidak, teks lama masuk lagi
      dan seluruh prosedur ini sia-sia.
- [ ] (Bila dibuat) hapus branch backup setelah beberapa hari tenang:
      `git branch -D backup-pre-rewrite`.

---

## Alternatif & kapan memakainya

| Cara | Cocok untuk | Catatan |
|---|---|---|
| `git filter-branch --msg-filter` (panduan ini) | repo kecil-menengah, tanpa install apa pun | bawaan git; lambat di repo besar; banyak jebakan (sudah dipetakan di atas) |
| [`git filter-repo`](https://github.com/newren/git-filter-repo) `--message-callback` | repo besar / rewrite kompleks | rekomendasi resmi git; lebih cepat & aman, tapi perlu install (Python). Contoh: `git filter-repo --message-callback 'return message.replace(b"lama", b"baru")'` |
| `git rebase -i` lalu `reword` | hanya 1–5 commit terakhir, belum ter-push | interaktif per commit; tidak praktis untuk 24 commit / commit awal |
| `git commit --amend` | hanya commit paling atas | kasus paling sederhana |
