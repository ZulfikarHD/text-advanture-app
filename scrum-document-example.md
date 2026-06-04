# Phase 1: Registrasi Nomor PO - Order Besar
## Labeling App - Peruri

**Timeline:** 1 Bulan (4 Sprints)
**Sprint Duration:** 1 Minggu
**Team Size Recommendation:** 1 Backend Dev, 1 Frontend Dev, 1 QA

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Registrasi Nomor PO | Critical | 34 | 1-2 |
| E2 | Integrasi API Sirine | Critical | 13 | 1 |
| E3 | Validasi & Error Handling | High | 13 | 2-3 |
| E4 | Kalkulasi Rim & Label | Critical | 18 | 3-4 |

**Total Estimated:** ~78 Story Points

---

## EPIC E1: Registrasi Nomor PO

### E1.1 - Halaman Registrasi Nomor PO

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | Sebagai **operator**, saya ingin **mengakses halaman registrasi nomor PO** agar **saya bisa mendaftarkan nomor PO baru ke sistem** | 3 | Critical | 1 |
| S-1.1.2 | Sebagai **operator**, saya ingin **melihat form input nomor PO dengan field yang sudah terisi default** agar **saya bisa langsung mengisi data tanpa setup manual** | 3 | Critical | 1 |
| S-1.1.3 | Sebagai **operator**, saya ingin **memilih team/workstation tujuan dari dropdown** agar **PO yang didaftarkan bisa ditugaskan ke team yang tepat** | 2 | Critical | 1 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Akses Halaman Registrasi Nomor PO

Scenario: Operator membuka halaman registrasi nomor PO
  Given saya sudah login sebagai operator
  When saya mengakses halaman "/register-po-pcht"
  Then saya melihat halaman "Register Nomor PO" dengan layout AuthenticatedLayout
  And halaman memuat form registrasi PO dalam BaseCard

Scenario: User belum login mengakses halaman
  Given saya belum login
  When saya mengakses "/register-po-pcht"
  Then saya diarahkan ke halaman "/login"
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Form Registrasi Nomor PO Default Values

Scenario: Form ditampilkan dengan nilai default
  Given saya berada di halaman registrasi nomor PO
  Then form menampilkan field berikut dengan nilai default:
    | Field       | Default Value | Status    |
    | po          | kosong        | enabled   |
    | obc         | kosong        | enabled   |
    | jml_lembar  | 0             | enabled   |
    | inschiet    | 0             | enabled   |
    | jml_rim     | 0             | disabled  |
    | start_rim   | 1             | enabled   |
    | end_rim     | 40            | enabled   |
    | team        | currentTeam   | enabled   |
    | seri        | 1             | hidden    |
    | produk      | PCHT          | hidden    |
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Pilih Team/Workstation

Scenario: Dropdown workstation menampilkan daftar team
  Given saya berada di halaman registrasi nomor PO
  Then dropdown "team" menampilkan semua workstation dari tabel workstation
  And workstation ditampilkan dengan kolom "id" dan "workstation" terurut berdasarkan nama

Scenario: Default team sesuai workstation user saat ini
  Given saya login sebagai operator dengan workstation_id = 3
  When saya membuka halaman registrasi nomor PO
  Then dropdown "team" secara default terpilih pada workstation_id 3
```

> **Catatan Teknis E1.1:**
> - **Business Logic:**
>   - Daftar workstation diambil dari master data workstation, diurutkan berdasarkan nama
>   - Setiap entry workstation memiliki minimal: `id` dan `nama`
>   - Default team yang terpilih pada dropdown = workstation yang ter-assign ke user yang sedang login
>   - Halaman hanya dapat diakses oleh user yang sudah terautentikasi

---

### E1.2 - Submit Registrasi Nomor PO

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | Sebagai **operator**, saya ingin **mensubmit form registrasi PO** agar **nomor PO terdaftar di sistem dan siap diproses** | 5 | Critical | 2 |
| S-1.2.2 | Sebagai **operator**, saya ingin **melihat dialog konfirmasi sebelum submit** agar **saya bisa memverifikasi data sebelum proses pendaftaran** | 3 | High | 2 |
| S-1.2.3 | Sebagai **operator**, saya ingin **form direset setelah submit berhasil** agar **saya bisa langsung mendaftarkan PO berikutnya** | 2 | High | 2 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Submit Registrasi Nomor PO

Scenario: Submit berhasil dengan data valid
  Given saya berada di halaman registrasi nomor PO
  When saya mengisi form:
    | Field      | Value         |
    | po         | 1234567890    |
    | obc        | ABC123456     |
    | jml_lembar | 20000         |
    | start_rim  | 1             |
    | end_rim    | 40            |
    | team       | 3             |
  And saya klik "Register"
  And saya konfirmasi dialog SweetAlert
  Then sistem mengirim POST ke "/api/register-po-pcht"
  And record baru terbuat di tabel generated_products:
    | Column        | Value       |
    | no_po         | 1234567890  |
    | no_obc        | ABC123456   |
    | type          | PCHT        |
    | sum_rim       | 40          |
    | start_rim     | 1           |
    | end_rim       | 40          |
    | assigned_team | 3           |
    | status        | 0           |
  And label records terbuat di tabel generated_labels untuk setiap rim (Kiri dan Kanan)
  And SweetAlert success ditampilkan

Scenario: Submit gagal karena PO sudah terdaftar
  Given nomor PO "1234567890" sudah terdaftar di tabel generated_products
  When saya mengisi form dengan nomor PO "1234567890"
  And saya klik "Register" dan konfirmasi
  Then sistem mengembalikan error 422
  And pesan error "Nomor PO sudah terdaftar" ditampilkan
```

**Acceptance Criteria - S-1.2.2:**
```gherkin
Feature: Dialog Konfirmasi Registrasi

Scenario: Dialog konfirmasi menampilkan detail PO
  Given saya sudah mengisi form registrasi dengan data valid
  When saya klik "Register"
  Then SweetAlert konfirmasi ditampilkan dengan informasi:
    | Info           | Nilai                            |
    | Jenis          | "Pusat" atau "Daerah" (berdasarkan seri) |
    | Seri           | Nomor seri dengan warna (hijau/merah)   |
  And tombol "OK" dan "Cancel" tersedia

Scenario: Batalkan submit dari dialog konfirmasi
  Given dialog konfirmasi SweetAlert ditampilkan
  When saya klik "Cancel"
  Then form tidak disubmit
  And data form tetap tersimpan
```

**Acceptance Criteria - S-1.2.3:**
```gherkin
Feature: Reset Form Setelah Submit

Scenario: Form direset setelah submit berhasil
  Given saya berhasil submit registrasi PO
  When SweetAlert success ditampilkan dan saya klik "OK"
  Then semua field form kembali ke nilai default:
    | Field       | Default Value |
    | po          | null          |
    | obc         | kosong        |
    | jml_lembar  | 0             |
    | inschiet    | 0             |
    | jml_rim     | 0             |
    | start_rim   | 1             |
    | end_rim     | 40            |
    | team        | currentTeam   |
```

> **Catatan Teknis E1.2:**
> - **Business Logic:**
>   - Proses registrasi PO harus bersifat atomic: jika ada satu langkah yang gagal, semua perubahan dibatalkan (transactional)
>   - Sistem menolak registrasi jika nomor PO sudah pernah terdaftar sebelumnya
>   - Setelah registrasi PO berhasil, sistem otomatis membuat:
>     1. Record produk dengan informasi: no_po, no_obc, type (PCHT), jumlah rim, range rim (start–end), team yang di-assign, dan status awal = 0
>     2. Label records untuk setiap rim, satu pasang per potongan (Kiri & Kanan)
>     3. Inschiet record + label inschiet jika ada sisa lembar yang tidak genap habis dibagi 1000
>   - Konstanta bisnis:
>     - 1 rim produk = 500 lembar
>     - 1 rim label = 1000 lembar
>     - Potongan label = `Kiri`, `Kanan`
>     - Nomor rim khusus untuk inschiet = 999
>   - Status awal PO baru = 0 (belum diproses)

---

## EPIC E2: Integrasi API Sirine

### E2.1 - Fetch Spesifikasi PO dari Sirine

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | Sebagai **operator**, saya ingin **data spesifikasi PO otomatis terisi saat mengetikkan nomor PO** agar **saya tidak perlu input manual dan data lebih akurat** | 5 | Critical | 1 |
| S-2.1.2 | Sebagai **operator**, saya ingin **field yang terisi dari Sirine menjadi disabled** agar **saya tidak bisa mengubah data yang sudah valid dari sumber resmi** | 3 | High | 1 |
| S-2.1.3 | Sebagai **operator**, saya ingin **melihat loading indicator saat data PO sedang diambil** agar **saya tahu sistem sedang memproses** | 2 | Medium | 1 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Auto-fetch Spesifikasi PO dari Sirine API

Scenario: Data PO berhasil diambil dari Sirine
  Given saya berada di halaman registrasi nomor PO
  When saya mengetikkan nomor PO "1234567890" pada field "po"
  And debounce 500ms selesai
  Then sistem memanggil GET "https://sirine.peruri.co.id/sirine/api/detail-order-pcht/1234567890"
  And field otomatis terisi:
    | Field      | Value dari Sirine          |
    | obc        | no_obc dari response       |
    | jml_lembar | rencet dari response       |
    | jml_rim    | dihitung dari jml_lembar   |
    | end_rim    | dihitung dari jml_lembar   |
    | inschiet   | dihitung dari jml_lembar   |

Scenario: Nomor PO tidak ditemukan di Sirine
  Given saya mengetikkan nomor PO yang tidak ada di Sirine
  When debounce 500ms selesai dan API mengembalikan error/empty
  Then field obc, jml_lembar tetap bisa diisi manual
  And tidak ada data otomatis yang terisi

Scenario: Debounce mencegah panggilan API berlebihan
  Given saya berada di field nomor PO
  When saya mengetik karakter secara cepat "123456"
  Then API hanya dipanggil sekali setelah 500ms dari karakter terakhir
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Field Disabled Setelah Data Sirine Terisi

Scenario: Field terisi dari Sirine menjadi read-only
  Given data PO berhasil diambil dari Sirine
  Then field berikut menjadi disabled:
    | Field      | Status   |
    | obc        | disabled |
    | jml_lembar | disabled |
    | end_rim    | disabled |
    | inschiet   | disabled |
  And field berikut tetap enabled:
    | Field      | Status  |
    | po         | enabled |
    | start_rim  | enabled |
    | team       | enabled |
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: Loading Indicator saat Fetch Data

Scenario: Loading overlay ditampilkan saat fetch API
  Given saya mengetikkan nomor PO dan debounce terpicu
  When API call sedang berjalan
  Then LoadingOverlay ditampilkan dengan spinner
  And LoadingOverlay memiliki minimum display time 75ms untuk mencegah flicker

Scenario: Loading overlay hilang setelah fetch selesai
  Given LoadingOverlay sedang ditampilkan
  When API call selesai (sukses atau gagal)
  Then LoadingOverlay disembunyikan
```

> **Catatan Teknis E2.1:**
> - **Business Logic:**
>   - Spesifikasi PO diambil dari sumber data eksternal Sirine (sistem master produksi Peruri)
>   - Pengambilan data dipicu otomatis dengan debounce 500ms saat user mengetik nomor PO
>   - Field yang dipakai dari response Sirine: `no_obc` (nomor OBC) dan `rencet` (jumlah lembar)
>   - Field lain pada form (`jml_rim`, `end_rim`, `inschiet`) dihitung otomatis dari `rencet`
>   - Akses ke Sirine memerlukan SSL certificate khusus (provided oleh tim infrastruktur Peruri)
>   - Hasil fetch boleh di-cache selama maksimal 30 menit untuk mengurangi load ke Sirine
>   - Jika Sirine tidak merespons atau PO tidak ditemukan, user tetap dapat mengisi field secara manual (graceful fallback)

**API Contract - S-2.1.1:**
```
GET https://sirine.peruri.co.id/sirine/api/detail-order-pcht/{no_po}

Headers:
  Accept: application/json

Response 200:
{
  "no_po": 1234567890,
  "no_obc": "ABC123456",
  "rencet": 20000,
  "jenis": "...",
  "mesin": "...",
  "desain": "..."
}

Response 404/Error:
  Empty response atau error object
```

---

## EPIC E3: Validasi & Error Handling

### E3.1 - Validasi Form Input

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | Sebagai **operator**, saya ingin **sistem memvalidasi nomor PO** agar **hanya nomor PO yang valid dan belum terdaftar yang bisa diproses** | 3 | Critical | 2 |
| S-3.1.2 | Sebagai **operator**, saya ingin **sistem memvalidasi format OBC** agar **nomor OBC yang diinput sesuai standar** | 2 | High | 2 |
| S-3.1.3 | Sebagai **operator**, saya ingin **melihat pesan error yang jelas dalam Bahasa Indonesia** agar **saya tahu persis apa yang salah dengan input saya** | 3 | High | 2 |
| S-3.1.4 | Sebagai **operator**, saya ingin **validasi range rim tidak melebihi batas** agar **tidak ada rim yang terdaftar melebihi kapasitas PO** | 3 | Critical | 3 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Validasi Nomor PO

Scenario: Nomor PO valid diterima
  Given saya mengisi nomor PO "1234567890" (10 karakter)
  When saya submit form
  Then validasi nomor PO lolos

Scenario: Nomor PO terlalu pendek
  Given saya mengisi nomor PO "12345" (kurang dari 10 karakter)
  When saya submit form
  Then error ditampilkan "Nomor PO minimal harus terdiri dari 10 karakter."

Scenario: Nomor PO terlalu panjang
  Given saya mengisi nomor PO lebih dari 20 karakter
  When saya submit form
  Then error ditampilkan "Nomor PO tidak boleh melebihi 20 karakter."

Scenario: Nomor PO sudah terdaftar
  Given nomor PO "1234567890" sudah ada di tabel generated_products
  When saya submit form dengan PO tersebut
  Then error 422 ditampilkan "Nomor PO sudah terdaftar."
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Validasi Format OBC

Scenario: OBC valid diterima
  Given saya mengisi OBC "ABC123456" (diawali 3 huruf, total 7-9 karakter)
  When saya submit form
  Then validasi OBC lolos

Scenario: OBC tidak diawali 3 huruf
  Given saya mengisi OBC "123456789"
  When saya submit form
  Then error ditampilkan "Format Nomor OBC tidak sesuai."

Scenario: OBC terlalu pendek
  Given saya mengisi OBC "ABC12" (kurang dari 7 karakter)
  When saya submit form
  Then error ditampilkan "Nomor OBC minimal harus terdiri dari 7 karakter."

Scenario: OBC terlalu panjang
  Given saya mengisi OBC "ABCDEFGHIJ" (lebih dari 9 karakter)
  When saya submit form
  Then error ditampilkan "Nomor OBC tidak boleh melebihi 9 karakter."

Scenario: Validasi frontend - cekSpec 3 karakter pertama alfabet
  Given saya mengisi OBC "123456789"
  When frontend menjalankan cekSpec()
  Then karakter pertama 3 huruf bukan alfabet
  And peringatan atau penolakan ditampilkan
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Pesan Error Bahasa Indonesia

Scenario: Error validasi ditampilkan dalam Bahasa Indonesia
  Given saya submit form dengan data tidak valid
  When server mengembalikan response 422
  Then SweetAlert error ditampilkan dengan pesan dari field "message": "Validasi gagal"
  And detail error per field ditampilkan

Scenario: Error umum saat proses gagal
  Given terjadi exception saat memproses registrasi
  When server mengembalikan error 422
  Then pesan "Terjadi kesalahan saat memproses permintaan. Silakan coba lagi." ditampilkan
```

**Acceptance Criteria - S-3.1.4:**
```gherkin
Feature: Validasi Range Rim

Scenario: Range rim dalam batas yang diizinkan
  Given jml_lembar = 20000 (max rim = ceil(20000/500) = 40)
  And start_rim = 1, end_rim = 40
  Then range rim (40 - 1 + 1 = 40) tidak melebihi max rim (40)
  And validasi lolos

Scenario: Range rim melebihi batas
  Given jml_lembar = 10000 (max rim = ceil(10000/500) = 20)
  And start_rim = 1, end_rim = 30
  Then range rim (30 - 1 + 1 = 30) melebihi max rim (20)
  And error "Range rim (30) melebihi jumlah rim yang diizinkan (20)." ditampilkan

Scenario: end_rim harus lebih besar atau sama dengan start_rim
  Given start_rim = 5
  When saya mengisi end_rim = 3
  And saya submit form
  Then error ditampilkan karena end_rim harus >= start_rim
```

> **Catatan Teknis E3.1:**
> - **Business Logic (Aturan Validasi):**
>   - `po`: wajib, panjang 10–20 karakter, harus unik (belum pernah terdaftar)
>   - `obc`: wajib, panjang 7–9 karakter, harus diawali 3 karakter alfabet (`[A-Za-z]{3}`)
>   - `team`: wajib, harus merujuk ke workstation yang valid di master data
>   - `produk`: wajib, maks 5 karakter (default: `PCHT`)
>   - `jml_lembar`: wajib, integer, minimum 1
>   - `jml_rim`: wajib, integer, minimum 1
>   - `start_rim`: wajib, integer, minimum 1
>   - `end_rim`: wajib, integer, harus ≥ `start_rim`, dan `(end_rim - start_rim + 1) ≤ ceil(jml_lembar / 500)`
>   - `inschiet`: opsional, integer, minimum 0
>   - `periksa1`, `periksa2`: opsional, string
> - **Response Format Error:**
>   - HTTP status: 422
>   - Body: `{ "message": "Validasi gagal", "errors": { "<field>": ["<pesan>"] } }`
>   - Semua pesan error wajib dalam Bahasa Indonesia
> - **Pesan Error Standar:**
>   - PO duplikat: "Nomor PO sudah terdaftar."
>   - Range rim melebihi batas: "Range rim ({requested}) melebihi jumlah rim yang diizinkan ({max})."
>   - Error umum saat proses gagal: "Terjadi kesalahan saat memproses permintaan. Silakan coba lagi."

---

## EPIC E4: Kalkulasi Rim & Label

### E4.1 - Kalkulasi Otomatis Rim

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | Sebagai **operator**, saya ingin **jumlah rim dihitung otomatis dari jumlah lembar** agar **saya tidak perlu menghitung manual** | 3 | Critical | 3 |
| S-4.1.2 | Sebagai **operator**, saya ingin **end_rim dan inschiet dihitung ulang saat start_rim berubah** agar **kalkulasi tetap konsisten** | 3 | Critical | 3 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Kalkulasi Otomatis Jumlah Rim

Scenario: Rim dihitung dari jumlah lembar
  Given jml_lembar = 20000
  Then jml_rim = floor(20000 / 500) = 40
  And field jml_rim ditampilkan sebagai disabled (read-only)

Scenario: Minimum rim adalah 1
  Given jml_lembar = 100 (kurang dari 500)
  Then jml_rim = max(floor(100/500), 1) = 1

Scenario: Perhitungan end_rim dari jumlah lembar
  Given jml_lembar = 20000 dan start_rim = 1
  Then end_rim = floor(20000 / 500) = 40

Scenario: Perhitungan inschiet dari sisa lembar
  Given jml_lembar = 20500
  Then inschiet = 20500 mod 1000 = 500
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Recalculate saat start_rim berubah

Scenario: start_rim berubah memicu kalkulasi ulang
  Given jml_lembar = 20000
  And start_rim = 1, end_rim = 40
  When saya mengubah start_rim menjadi 5
  Then calcEndRim() dipanggil
  And end_rim, jml_rim, inschiet dihitung ulang berdasarkan jml_lembar dan start_rim baru
```

> **Catatan Teknis E4.1:**
> - **Business Logic (Formula Kalkulasi):**
>   - `jml_rim = max(floor(jml_lembar / 500), 1)` — minimal selalu 1
>   - `end_rim = start_rim + jml_rim - 1`
>   - `inschiet = jml_lembar mod 1000` — sisa lembar yang tidak genap habis dibagi 1000
>   - Trigger recalculation: setiap kali `jml_lembar` atau `start_rim` berubah
>   - Field `jml_rim` selalu read-only di UI (hasil kalkulasi, bukan input langsung)
>   - Konstanta bisnis:
>     - 1 rim produk = 500 lembar (untuk perhitungan jumlah rim)
>     - 1 rim label = 1000 lembar (untuk perhitungan inschiet)

---

### E4.2 - Generate Label Records

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | Sebagai **sistem**, saya ingin **label records otomatis dibuat saat PO didaftarkan** agar **label siap digunakan untuk proses cetak** | 5 | Critical | 3 |
| S-4.2.2 | Sebagai **sistem**, saya ingin **data inschiet dicatat secara otomatis** agar **sisa lembar yang tidak genap 1 rim tetap tercatat** | 5 | Critical | 4 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Generate Label Records Otomatis

Scenario: Label dibuat untuk setiap rim dengan potongan Kiri dan Kanan
  Given PO didaftarkan dengan start_rim=1, end_rim=40, jml_lembar=20000
  When proses registrasi selesai
  Then tabel generated_labels terisi dengan records:
    | no_po_generated_products | no_rim | potongan |
    | {no_po}                  | 1      | Kiri     |
    | {no_po}                  | 1      | Kanan    |
    | {no_po}                  | 2      | Kiri     |
    | {no_po}                  | 2      | Kanan    |
    | ...                      | ...    | ...      |
    | {no_po}                  | 40     | Kiri     |
    | {no_po}                  | 40     | Kanan    |
  And total records = (end_rim - start_rim + 1) × 2

Scenario: Label tidak dibuat jika jumlah lembar <= 1000
  Given PO didaftarkan dengan jml_lembar = 800
  When proses registrasi selesai
  Then hanya inschiet labels yang dibuat (rim 999)
  And generateLabels() tidak dipanggil

Scenario: Proses registrasi dalam satu transaksi database
  Given PO valid disubmit
  When terjadi error saat generate labels
  Then semua perubahan di-rollback (DB::transaction)
  And tidak ada record baru di generated_products maupun generated_labels
```

**Acceptance Criteria - S-4.2.2:**
```gherkin
Feature: Generate Data Inschiet

Scenario: Inschiet dicatat jika ada sisa lembar
  Given PO didaftarkan dengan jml_lembar = 20500
  When proses registrasi selesai
  Then record data_inschiet dibuat:
    | Column   | Value          |
    | no_po    | {no_po}        |
    | inschiet | 500            |
    | np_kiri  | null           |
    | np_kanan | null           |
  And inschiet labels dibuat di generated_labels dengan no_rim = 999

Scenario: Inschiet label dibuat dengan potongan Kiri dan Kanan
  Given inschiet > 0
  When insertInschiet() dipanggil
  Then label rim 999 dibuat:
    | no_rim | potongan |
    | 999    | Kiri     |
    | 999    | Kanan    |

Scenario: Tidak ada inschiet jika lembar habis dibagi 1000
  Given jml_lembar = 20000 (20000 mod 1000 = 0)
  When proses registrasi selesai
  Then tidak ada record data_inschiet yang dibuat
  And tidak ada label rim 999
```

> **Catatan Teknis E4.2:**
> - **Business Logic:**
>   - **Generate Rim Labels:**
>     - Untuk setiap nomor rim dari `start_rim` sampai `end_rim`, sistem membuat 2 record label: satu untuk potongan `Kiri` dan satu untuk potongan `Kanan`
>     - Total label rim = `(end_rim - start_rim + 1) × 2`
>     - Setiap label rim berisi: nomor PO, nomor rim, potongan, dan field opsional (np_users, start, finish, workstation) yang null saat dibuat
>     - Generate rim labels hanya dijalankan jika `jml_lembar > 1000`
>   - **Generate Inschiet:**
>     - `inschiet = jml_lembar mod 1000`
>     - Jika `inschiet > 0`, sistem membuat 1 record inschiet (no_po, inschiet, np_kiri=null, np_kanan=null) DAN 2 record label inschiet (rim = 999, potongan Kiri + Kanan)
>     - Jika `inschiet = 0`, tidak ada record inschiet maupun label rim 999 yang dibuat
>   - **Atomic Transaction:**
>     - Pembuatan record produk + rim labels + inschiet data + inschiet labels harus berada dalam satu transaksi
>     - Jika salah satu langkah gagal, seluruh perubahan harus di-rollback
>   - Nomor rim khusus untuk inschiet = `999` (reserved)
>   - Potongan yang didukung = `Kiri`, `Kanan`

---

## Sprint Roadmap

### Sprint 1: Setup & Integrasi (E1.1 + E2.1)
```
Sprint 1 (Week 1):
├── S-1.1.1: Akses halaman registrasi PO
├── S-1.1.2: Form dengan default values
├── S-1.1.3: Dropdown workstation
├── S-2.1.1: Auto-fetch dari Sirine API
├── S-2.1.2: Disable field dari Sirine
├── S-2.1.3: Loading indicator
└── Integration testing halaman & API Sirine
```

### Sprint 2: Submit & Validasi (E1.2 + E3.1)
```
Sprint 2 (Week 2):
├── S-1.2.1: Submit registrasi PO
├── S-1.2.2: Dialog konfirmasi
├── S-1.2.3: Reset form
├── S-3.1.1: Validasi nomor PO
├── S-3.1.2: Validasi format OBC
├── S-3.1.3: Pesan error Bahasa Indonesia
└── Integration testing submit flow
```

### Sprint 3: Kalkulasi & Label (E3.1 + E4.1 + E4.2)
```
Sprint 3 (Week 3):
├── S-3.1.4: Validasi range rim
├── S-4.1.1: Kalkulasi otomatis rim
├── S-4.1.2: Recalculate start_rim
├── S-4.2.1: Generate label records
└── Integration testing kalkulasi & label generation
```

### Sprint 4: Finalisasi (E4.2 + QA)
```
Sprint 4 (Week 4):
├── S-4.2.2: Data inschiet
├── End-to-end testing
├── Edge case testing
└── Performance & regression testing
```

---

## Definition of Done (DoD)

Setiap user story dianggap **DONE** jika:

- [ ] Code sudah di-review oleh minimal 1 developer lain
- [ ] Unit tests written dan passing (coverage > 80%)
- [ ] Integration tests passing
- [ ] No critical/high bugs dari QA
- [ ] UI responsive (mobile + desktop)
- [ ] Performance: page load < 2s
- [ ] Acceptance criteria terpenuhi semua
- [ ] Deployed ke staging
- [ ] Product Owner approved

---

## Success Metrics - Phase 1

| Metric | Target | Measurement |
|--------|--------|-------------|
| Registrasi PO berhasil rate | > 95% | Total berhasil / total submit |
| Waktu registrasi per PO | < 30 detik | Rata-rata waktu input hingga submit |
| Error validasi rate | < 10% | Total error validasi / total submit |
| Sirine API response time | < 3 detik | Rata-rata response time fetch Sirine |
| Label generation accuracy | 100% | Label count match dengan kalkulasi rim |

---

## Risk Register

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Sirine API down/tidak responsif | Critical | Medium | Fallback ke input manual, cache response selama 30 menit |
| SSL certificate expired untuk akses Sirine | Critical | Low | Monitoring cert expiry, auto-renewal alert |
| Endpoint registrasi PO dapat diakses tanpa autentikasi | High | High | Wajib lindungi endpoint registrasi dengan autentikasi |
| Duplikat label generation pada concurrent submit | High | Low | Atomic transaction + unique constraint pada nomor PO |
| Data inschiet tidak konsisten dengan label | Medium | Low | Validasi di domain/business layer, unit test untuk edge cases |

---

*Document Version: 1.0*
*Author: Zulfikar Hidayatullah*
*Created: May 2026*
