# Documentation Structure Standard — Aligator v2 (Labeling App Peruri)

> **Status:** Authoritative · **Author:** Zulfikar Hidayatullah (+62 857-1583-8733) · **Last Updated:** 2026-06-02
>
> Dokumen ini adalah **standar resmi** untuk struktur folder `docs/` pada aplikasi **Aligator v2** (Labeling App Peruri). Tujuannya: setiap developer tahu **di mana** sebuah dokumen harus ditaruh, **bagaimana** menamainya, dan **kapan** membuatnya — sehingga knowledge base tetap konsisten dan mudah dinavigasi.
>
> Diadaptasi dari standar dokumentasi Mainow Team agar sesuai dengan domain & peran aplikasi ini.

> 📎 **Dokumen terkait:**
> - [README.md](./README.md) — entry point project & quick start
> - Skill `code-documentation` (`.cursor/skills/code-documentation/SKILL.md`) — standar docstring/PHPDoc/JSDoc inline

---

## 1. Filosofi

1. **Satu sumber kebenaran** — setiap informasi punya satu rumah. Jangan duplikat; gunakan cross-reference.
2. **Lokasi = jenis dokumen** — folder menentukan tipe, nama file menentukan subjek + kode/tanggal.
3. **Hierarki untuk navigasi** — pengelompokan berbasis peran (`admin`/`operator`) dan domain (PCHT, MMEA, Inspeksi, Kerusakan).
4. **Append-only untuk audit & keputusan** — ADR, security log, dan business logic log tidak diedit ulang; buat entri baru.
5. **Dokumentasi mengikuti kode** — docstring inline (skill `code-documentation`) tetap jadi lapisan pertama; `docs/` untuk knowledge lintas-file.

---

## 2. Peran & Domain Aplikasi

Aplikasi ini hanya punya dua **role** di database (`users.role` enum `admin`|`user`):

| Role DB | Sebutan dokumentasi | Cakupan |
|---------|---------------------|---------|
| `admin` | `admin` | User Management, master data (Jenis Kerusakan), konfigurasi sistem |
| `user` | `operator` | Lantai produksi: registrasi PO, siap periksa, cetak label, entry kerusakan |

> "Supervisor" bukan role terpisah — hanya **tampilan monitoring** (Dashboard, Team Performance, Live Production) yang diakses sesuai izin. Dokumen monitoring dikelompokkan di bawah peran yang relevan (umumnya `admin`) atau sebagai resource lintas-peran.

**Domain utama:** `pcht` (Pita Cukai Hasil Tembakau), `mmea` (Minuman Mengandung Etil Alkohol), `inspeksi`, `kerusakan`, `production-order`, `workstation/team`.

---

## 3. Peta Struktur

```
docs/
│
├── 📄 README.md                       # Entry point: overview, tech stack, quick start
├── 📄 DOCUMENTATION_STRUCTURE.md      # Standar struktur folder (dokumen ini)
│
├── 📁 architecture/                   # System design, data flow, database, diagram
│   ├── README.md
│   ├── ARCHITECTURE.md                # (opsional) pattern, layer, service
│   ├── DATABASE.md                    # (opsional) schema & relasi
│   └── Diagrams/                      # Diagram per peran (Mermaid)
│       ├── Admin/
│       └── Operator/                  # cth: Operator/Register_Po_Pcht.md
│
├── 📁 adr/                            # Architecture Decision Records (immutable)
│   ├── README.md                      # Index + template ADR
│   └── NNN-kebab-title.md             # cth: 001-ulid-untuk-label-control.md
│
├── 📁 api/                            # Kontrak API per resource / per peran
│   ├── README.md
│   ├── admin/  · operator/            # cth: operator/operator-siap-periksa.md
│   └── <resource>.md                  # resource lintas-peran (register-po-pcht.md, ...)
│
├── 📁 features/                       # Spesifikasi fitur (lean, ≤300 baris)
│   ├── README.md
│   ├── admin/  · operator/            # cth: operator/E1.1-register-po-pcht.md
│   └── _templates/                    # feature-template.md
│
├── 📁 testing/                        # Test plan & QA artefak
│   ├── README.md
│   ├── <CODE>-{slug}-test-plan.md     # cth: E1.1-register-po-pcht-test-plan.md
│   └── _templates/
│
├── 📁 guides/                         # How-to, onboarding, user journey, diagnostics
│   ├── README.md
│   ├── PLACEHOLDER_TRACKING.md        # Tracking placeholder & divergensi desain
│   ├── <feature>-onboarding.md        # cth: register-po-pcht-onboarding.md
│   └── <feature>-user-journeys.md
│
├── 📁 manual-qa-check/                # Hasil manual QA per story (bukti)
│   ├── README.md
│   ├── admin-side/                    # cth: E?.?-*.md
│   └── operator-side/                 # cth: E1.1-register-po-pcht.md
│
├── 📁 business_logic_logs/            # Audit business logic (append-only)
│   └── BL-audit-YYYY-MM-DD-<code>-<slug>.md
│
├── 📁 security_logs/                  # Audit keamanan OWASP (append-only)
│   └── OWASP-audit-YYYY-MM-DD-<code>-<slug>.md
│
├── 📁 runbooks/                       # Prosedur operasional / diagnostik produksi
│   └── <topic>-diagnostics.md         # cth: register-po-pcht-diagnostics.md
│
└── 📁 reviews/                        # (opsional) catatan review UX/fitur
    └── <area>-ux-review.md
```

> **Catatan migrasi v1 → v2:** repo lama menaruh `security_logs/` dan `business_logic_logs/` di **root project**. Untuk v2, audit baru ditaruh di **`docs/`** sesuai standar ini. Log root dianggap arsip v1 (frozen).

---

## 4. Referensi Folder

| Folder | Isi / Tujuan | Konvensi Nama | Kapan menambah file | Lifecycle |
|--------|--------------|---------------|---------------------|-----------|
| **(root)** | Meta-dokumen: entry point & standar | `UPPER_SNAKE.md` | Saat setup/standar berubah | Editable |
| `architecture/` | Desain sistem, layer, data flow, schema, diagram | Core: `ARCHITECTURE.md`, `DATABASE.md`. Diagram: `Diagrams/{Role}/{Subject}.md` | Saat pattern/schema/alur lintas-domain berubah | Editable |
| `adr/` | Keputusan arsitektur + alasannya | `NNN-kebab-title.md` (3 digit) + `README.md` | Setiap keputusan arsitektur/library | **Immutable** (buat ADR baru yang *supersede*) |
| `api/` | Kontrak endpoint (request/response/error + props Inertia) | `{role}/{role}-{resource}.md` atau `{resource}.md` | Setiap endpoint/resource berubah | Editable |
| `features/` | Spesifikasi fitur lean (≤300 baris) | `{role}/{CODE}-{slug}.md` | Setiap fitur baru/dimodifikasi | Editable |
| `testing/` | Test plan & QA checklist | `{CODE}-{slug}-test-plan.md` | Bersamaan dengan setiap fitur | Editable |
| `guides/` | How-to, onboarding, user journey, diagnostics, tracking | `{feature}-onboarding.md`, `{feature}-user-journeys.md`, `PLACEHOLDER_TRACKING.md` | Saat butuh panduan langkah/alur | Editable |
| `manual-qa-check/` | Bukti hasil manual QA per story | `{side}/{CODE}-{slug}.md` (`admin-side`/`operator-side`) | Setelah QA manual sebuah story | Append (per story) |
| `business_logic_logs/` | Audit integritas business logic | `BL-audit-YYYY-MM-DD-{code}-{slug}.md` | Setelah audit business logic | **Append-only** |
| `security_logs/` | Audit OWASP Top 10 | `OWASP-audit-YYYY-MM-DD-{code}-{slug}.md` | Setelah audit keamanan story | **Append-only** |
| `runbooks/` | Prosedur ops/diagnostik produksi | `{topic}-diagnostics.md` | Saat butuh playbook insiden/integrasi | Editable |
| `reviews/` | Catatan review UX/fitur | `{area}-ux-review.md` | Saat melakukan review terstruktur | Editable |

---

## 5. Konvensi Penamaan

### 5.1 Kode Fitur & Story (mengikuti dokumen scrum `scrum/`)

Aplikasi ini sudah memakai penomoran scrum **Phase → Epic → Story**. Dokumentasi mengikutinya agar mudah ditelusuri lintas folder.

| Prefix | Arti | Lingkup | Contoh |
|--------|------|---------|--------|
| `E{n}.{m}` | Epic (Phase n, sub-epic m) | Pengelompokan fitur | `E1.1-register-po-pcht.md` |
| `S-{e}.{s}.{n}` | Story | Story QA/feature | `S-1.1.3` (dropdown workstation) |

> Story ID berformat `{epic}.{group}.{urutan}` (cth: `1.1.3`) — konsisten dengan `scrum/labeling-app-phase-1-register-nomor-po.md`. Pakai kode yang sama di `features/`, `testing/`, `manual-qa-check/`, dan log audit.

### 5.2 File Audit (Dated, Append-only)

```
{TYPE}-audit-{YYYY-MM-DD}-{code}-{slug}.md
```

| Type | Folder | Contoh |
|------|--------|--------|
| `BL` | `business_logic_logs/` | `BL-audit-2026-06-02-e1.1-register-po-pcht.md` |
| `OWASP` | `security_logs/` | `OWASP-audit-2026-06-02-e1.1-register-po-pcht.md` |

- **Tanggal = `YYYY-MM-DD`** (Asia/Jakarta) agar terurut leksikografis.
- **Jangan edit file lama** — temuan baru = file baru. Arsipkan periode lama ke subfolder (cth: `2026-q2/`).

### 5.3 ADR

- Format `NNN-kebab-title.md`, nomor **3 digit berurutan** (`001`…).
- **Immutable**: keputusan berubah → ADR baru yang menyatakan *supersedes #NNN*.
- Daftarkan setiap ADR di `adr/README.md`.

### 5.4 Aturan Umum

- Markdown `.md`, nama `kebab-case` untuk konten; meta-doc root = `UPPER_SNAKE`.
- Folder berbasis peran selalu lowercase: `admin/`, `operator/`, `*-side/`.
- Folder template diawali underscore: `_templates/`.
- Folder `Diagrams/{Role}/` memakai PascalCase peran + `Title_Case` subjek (cth: `Operator/Register_Po_Pcht.md`).
- Setiap folder utama punya `README.md` sebagai index.
- **Case-sensitive** — patuhi kapitalisasi yang sudah ada (rule developer: "always watch for the case sensitivity").

---

## 6. Aturan "Satu Fitur = Banyak Dokumen"

Saat membangun satu fitur, dokumen **tersebar** sesuai jenisnya (jangan jadi satu file gemuk):

```
features/{role}/{CODE}-{slug}.md           → spesifikasi lean (≤300 baris) + Related Documentation
api/{resource}.md (atau {role}/...)        → kontrak endpoint + props Inertia
testing/{CODE}-{slug}-test-plan.md         → QA checklist (map ke test otomatis)
guides/{feature}-onboarding.md             → walkthrough developer
guides/{feature}-user-journeys.md          → alur operator (opsional)
manual-qa-check/{side}/{CODE}-{slug}.md    → bukti QA manual (navigasi UI, no direct-URL)
business_logic_logs/BL-audit-*.md          → audit business logic
security_logs/OWASP-audit-*.md             → audit keamanan
architecture/Diagrams/{Role}/{Subject}.md  → ERD + sequence/flow (Mermaid)
runbooks/{topic}-diagnostics.md            → playbook ops/insiden (jika perlu)
```

> Feature doc **WAJIB** punya section `Related Documentation` yang menaut dokumen-dokumen di atas (path relatif).

**Contoh lengkap (referensi):** Epic **E1.1 — Halaman Registrasi Nomor PO** sudah didokumentasikan mengikuti pola ini — lihat [`features/operator/E1.1-register-po-pcht.md`](./features/operator/E1.1-register-po-pcht.md).

---

## 7. Pohon Keputusan Cepat

| Saya baru saja… | Taruh di |
|------------------|----------|
| Mengubah tech stack / setup | `README.md` |
| Mengambil keputusan arsitektur/library | `adr/NNN-*.md` (baru) |
| Mengubah pattern/layer/data flow | `architecture/*` |
| Mengubah schema database | `architecture/DATABASE.md` + `architecture/Diagrams/{Role}/*` |
| Membuat/mengubah endpoint atau props Inertia | `api/{resource}.md` |
| Menyelesaikan fitur (epic/story) | `features/{role}/{CODE}-*.md` (+ dokumen turunan §6) |
| Membuat test plan | `testing/{CODE}-{slug}-test-plan.md` |
| Menulis onboarding/alur/cara pakai | `guides/...` |
| Meninggalkan placeholder / divergensi desain | `guides/PLACEHOLDER_TRACKING.md` |
| Selesai QA manual sebuah story | `manual-qa-check/{side}/...` |
| Selesai audit business logic | `business_logic_logs/BL-audit-*.md` |
| Selesai audit keamanan | `security_logs/OWASP-audit-*.md` |
| Menulis prosedur insiden/ops | `runbooks/{topic}-diagnostics.md` |
| Review UX | `reviews/{area}-ux-review.md` |

---

## 8. Maintenance & Catatan Konsistensi

- Update `README.md` root + `README.md` folder terkait setiap menambah dokumen baru.
- Pastikan cross-reference antar dokumen valid (gunakan path relatif).
- Arsipkan log audit per periode begitu folder mulai padat.
- Standar `code-documentation` (docstring inline) tetap wajib di level kode; `docs/` melengkapi, bukan menggantikan.

---

**Author: Zulfikar Hidayatullah** · Standar ini berlaku untuk seluruh kontribusi dokumentasi di `docs/` pada Aligator v2.
