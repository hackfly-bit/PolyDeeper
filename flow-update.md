# Flow Update Polymarket Authentication

## Tujuan

Merapikan flow autentikasi Polymarket agar sesuai dengan dokumentasi resmi:

1. User hanya mengisi data dasar account.
2. L2 credential (`apiKey`, `secret`, `passphrase`) tidak diinput manual.
3. Tombol `Validate` menjalankan flow L1 untuk `create or derive API key`, lalu langsung menyimpan hasilnya ke tabel `polymarket_accounts`.
4. Runtime trading hanya memakai account aktif dari tabel `polymarket_accounts`, tanpa fallback global yang membingungkan.

Referensi doc Polymarket yang harus dijadikan acuan:

- `Authentication`: L1 memakai private key untuk membuat/derive API credential, L2 memakai `apiKey + secret + passphrase`.
- `L1 Methods`: `createApiKey`, `deriveApiKey`, `createOrDeriveApiKey`.
- `Trading Overview` / `Quickstart`: flow awal yang direkomendasikan adalah `private key -> derive/create L2 credential -> pakai L2 untuk trade`.

## Ringkasan Audit

### Yang sudah benar

- Sudah ada pemisahan model account ke tabel `polymarket_accounts`.
- L2 credential sudah disimpan per-account di DB, bukan dipaksa lewat `.env`.
- Sudah ada action `validate`, `revoke`, `disable trading`, `enable trading`.
- Sudah ada orchestrator untuk memilih account aktif.

### Yang belum sesuai doc Polymarket

1. Flow UI masih meminta input L2 manual.
   - `resources/views/dashboard/polymarket-accounts/show.blade.php` masih punya form `API Key`, `API Secret`, `API Passphrase`.
   - Ini bertentangan dengan flow doc Polymarket yang menempatkan L2 credential sebagai hasil dari L1 auth, bukan input manual user.

2. Method `validate` belum melakukan bootstrap L1 -> L2.
   - `app/Services/Polymarket/PolymarketCredentialService.php` saat ini melempar error jika `api_key/api_secret/api_passphrase` belum ada.
   - Seharusnya `validate` justru:
     - resolve private key signer,
     - sign request L1,
     - panggil endpoint create/derive API credential,
     - simpan hasil ke DB,
     - baru validasi endpoint L2.

3. Masih ada flow global credentials di `SystemSetting`.
   - `DashboardController::settings()` dan `DashboardController::updatePolymarketSettings()` masih membaca/menulis:
     - `polymarket.address`
     - `polymarket.funder`
     - `polymarket.api_key`
     - `polymarket.api_secret`
     - `polymarket.api_passphrase`
     - `polymarket.signature_type`
   - Ini membuat runtime bisa ambigu karena sebagian code memakai account aktif, sebagian lagi fallback ke global setting.

4. Implementasi L1 signing belum sesuai requirement doc.
   - Doc Polymarket menyebut L1 harus memakai EIP-712 signature.
   - `app/Services/Polymarket/SigningService.php` saat ini memakai `hash_hmac('sha256', ...)` untuk `signL1Message()` dan `signEip712Payload()`.
   - Itu bukan ECDSA secp256k1 / EIP-712 signature yang valid untuk Polymarket.
   - Ini blocker utama untuk flow auth yang benar.

5. `PolymarketAuthService` baru lengkap untuk L2, belum lengkap untuk L1 onboarding.
   - Sudah ada `buildL1Headers()`, tetapi belum ada flow lengkap:
     - build typed data L1,
     - sign EIP-712,
     - call `POST /auth/api-key`,
     - fallback `GET /auth/derive-api-key`,
     - atau helper `createOrDeriveApiKey()`.

6. Ada fungsi/route/command lama yang akan menjadi tidak relevan jika flow baru diterapkan.
   - `PolymarketAccountController::storeCredentials()`
   - route `settings.polymarket.accounts.credentials.store`
   - form manual L2 credential di halaman detail account
   - `DashboardController::updatePolymarketSettings()`
   - route `settings.polymarket.update`
   - `PolymarketConfigService` jika runtime sudah full per-account
   - command `polymarket:set-credentials`

## Flow Target Yang Benar

### Flow user

1. User buat account baru.
2. User hanya isi field dasar:
   - `name`
   - `wallet_address`
   - `signature_type`
   - `funder_address` bila diperlukan untuk proxy/safe
   - sumber private key signer
3. User klik `Validate`.
4. Backend:
   - resolve private key signer,
   - buat signature L1 sesuai doc,
   - panggil `derive` atau `create` API credential,
   - simpan `api_key`, `api_secret`, `api_passphrase` ke `polymarket_accounts`,
   - hit endpoint L2 sederhana untuk memastikan credential valid,
   - update `credential_status`, `last_validated_at`, `last_error_code`.
5. Setelah itu account bisa dipilih sebagai account aktif dan dipakai runtime trading.

### Catatan UX soal "private key"

Agar tetap aman, private key sebaiknya **tidak disimpan plaintext di DB aplikasi**.

Pilihan yang paling aman:

- user hanya mengisi `wallet_address` + `env_key_name` / `secret reference`
- backend membaca private key dari env / vault

Kalau mau benar-benar menerima private key dari form UI, maka itu harus dianggap scope terpisah karena akan butuh:

- penyimpanan terenkripsi khusus,
- rotasi,
- masking,
- audit,
- aturan akses yang jauh lebih ketat

Untuk plan ini, opsi yang paling konsisten dengan codebase saat ini adalah:

- input minimal user = `wallet_address` + referensi private key backend
- bukan input manual L2 credential

## Perubahan Yang Harus Dilakukan

### 1. Sederhanakan model input account

File terdampak:

- `app/Http/Controllers/PolymarketAccountController.php`
- `app/Services/Polymarket/PolymarketAccountService.php`
- `resources/views/dashboard/polymarket-accounts/index.blade.php`
- `resources/views/dashboard/polymarket-accounts/show.blade.php`

Perubahan:

- Jadikan field create/edit fokus ke data inti auth:
  - `name`
  - `wallet_address`
  - `signature_type`
  - `funder_address`
  - `env_key_name` atau secret ref yang jelas namanya
- Pindahkan field runtime/risk (`priority`, `risk_profile`, `max_exposure_usd`, `max_order_size`, `cooldown_seconds`) ke section terpisah jika memang masih dibutuhkan untuk execution.
- Hapus form manual input:
  - `api_key`
  - `api_secret`
  - `api_passphrase`

### 2. Ubah `validate` menjadi flow onboarding resmi

File terdampak:

- `app/Services/Polymarket/PolymarketCredentialService.php`
- `app/Services/Polymarket/PolymarketAuthService.php`
- `app/Services/Polymarket/PolymarketService.php`

Perubahan:

- Tambahkan method baru dengan bentuk kira-kira:
  - `createOrDeriveCredentials(PolymarketAccount $account): array`
  - `createApiCredentialsViaL1(PolymarketAccount $account): array`
  - `deriveApiCredentialsViaL1(PolymarketAccount $account): array`
- Ubah `validateCredentials()` menjadi:
  1. resolve signer private key
  2. create/derive L2 credential via L1
  3. simpan hasil ke account
  4. validasi endpoint L2
  5. update status account

Urutan yang direkomendasikan:

1. Coba `derive` dulu untuk nonce default.
2. Jika belum ada credential, baru `create`.
3. Jika `create` sukses, simpan hasilnya.
4. Lanjut hit endpoint L2 seperti `GET /data/orders`.

Alasan:

- sesuai konsep `createOrDeriveApiKey()` yang direkomendasikan Polymarket,
- aman untuk retry,
- menghindari rotasi yang tidak perlu.

### 3. Implementasikan signing L1 yang benar

File terdampak:

- `app/Services/Polymarket/SigningService.php`
- kemungkinan service/helper baru untuk EIP-712 signing

Perubahan wajib:

- Ganti implementasi pseudo-signing berbasis HMAC menjadi ECDSA secp256k1 yang benar.
- L1 auth Polymarket harus menghasilkan signature EIP-712 yang valid.
- Order signing juga harus diaudit ulang karena saat ini `signEip712Payload()` belum terlihat sesuai mekanisme EIP-712 sesungguhnya.

Status:

- Ini adalah blocker teknis paling penting.
- Tanpa ini, flow auth yang terlihat "berhasil" tetap berpotensi gagal saat melawan endpoint Polymarket yang asli.

### 4. Hapus flow global `SystemSetting` untuk auth trading

File terdampak:

- `app/Http/Controllers/DashboardController.php`
- `app/Services/Polymarket/PolymarketConfigService.php`
- `resources/views/dashboard/settings.blade.php`
- `tests/Feature/SettingsPolymarketTest.php`
- `tests/Feature/Unit/PolymarketConfigServiceTest.php`
- `app/Console/Commands/PolymarketSetCredentialsCommand.php`

Perubahan:

- Hentikan penyimpanan auth trading ke `system_settings`.
- Halaman `settings` cukup menampilkan:
  - account aktif,
  - status credential,
  - signer/funder,
  - ringkasan runtime,
  - link ke manajemen account
- Hapus form global credential update.
- Hapus fallback runtime dari global config ke account config.

Target akhir:

- satu-satunya sumber auth trading = `polymarket_accounts`
- `system_settings` tidak lagi menyimpan credential trading Polymarket

### 5. Rapikan controller, route, dan action yang tidak perlu

File terdampak:

- `app/Http/Controllers/PolymarketAccountController.php`
- `routes/web.php`

Hapus:

- `storeCredentials()`
- route `settings.polymarket.accounts.credentials.store`
- `DashboardController::updatePolymarketSettings()`
- route `settings.polymarket.update`

Pertahankan:

- `store`
- `update`
- `validate`
- `revoke`
- `disableTrading`
- `enableTrading`
- `health`
- `selectPolymarketAccount`

Opsional evaluasi:

- `rotateCredentials()` bisa dipertahankan jika memang mau ada status administratif `needs_rotation`.
- Tetapi jika flow akhir hanya `validate => derive/create => active`, maka `rotate` bukan kebutuhan utama onboarding.

### 6. Rapikan schema / field DB yang tidak dipakai untuk auth

Field yang **tetap dibutuhkan** untuk flow auth:

- `name`
- `wallet_address`
- `funder_address`
- `signature_type`
- `env_key_name` atau field pengganti yang lebih jelas
- `api_key`
- `api_secret`
- `api_passphrase`
- `credential_status`
- `last_error_code`
- `last_validated_at`
- `last_rotated_at` opsional
- `is_active`

Field yang **bukan bagian auth onboarding** dan perlu dipisahkan secara mental:

- `priority`
- `risk_profile`
- `max_exposure_usd`
- `max_order_size`
- `cooldown_seconds`
- `cooldown_until`
- `auth_failure_count`
- `rate_limit_hit_count`

Rekomendasi:

- jangan hapus field runtime tersebut kalau memang dipakai execution,
- tetapi pisahkan dari flow auth supaya user tidak merasa semua field itu wajib diisi untuk onboarding.

Field yang perlu dievaluasi untuk dihapus/rename:

- `vault_key_ref`
  - saat ini belum benar-benar di-resolve oleh `SecretResolverService`
  - jika tidak dipakai, hapus
  - jika mau dipakai, implementasikan resolver nyata

## Urutan Implementasi

### Phase 1 - Audit dan pemutusan flow lama

1. Hapus form global credential dari `settings`.
2. Hapus route/controller global update credentials.
3. Hapus form manual L2 credential dari detail account.
4. Pastikan semua tampilan hanya membaca data dari account aktif.

### Phase 2 - Auth onboarding yang benar

1. Implementasikan L1 typed-data signing sesuai doc.
2. Tambahkan service untuk `derive/create API credential`.
3. Ubah tombol `Validate` menjadi:
   - bootstrap L1 -> L2
   - validasi L2
   - simpan hasil ke DB

### Phase 3 - Runtime cleanup

1. Pastikan `PolymarketService` dan `OrderExecutionService` hanya memakai account aktif.
2. Kurangi dependency ke `PolymarketConfigService`.
3. Hapus command/fungsi/tes lama yang bergantung ke `system_settings`.

### Phase 4 - Test coverage

Tambahkan/update test untuk skenario berikut:

1. Create account hanya dengan data dasar.
2. Validate account tanpa L2 credential existing.
3. Validate memanggil flow derive/create L1 dan menyimpan hasil ke DB.
4. Validate gagal jika private key backend tidak tersedia.
5. Re-validate account existing tidak meminta user input ulang L2 credential.
6. Runtime settings page tidak lagi menampilkan form credential global.

## Dampak ke File Saat Implementasi

### Wajib diubah

- `app/Http/Controllers/PolymarketAccountController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Services/Polymarket/PolymarketCredentialService.php`
- `app/Services/Polymarket/PolymarketAuthService.php`
- `app/Services/Polymarket/SigningService.php`
- `resources/views/dashboard/settings.blade.php`
- `resources/views/dashboard/polymarket-accounts/index.blade.php`
- `resources/views/dashboard/polymarket-accounts/show.blade.php`
- `routes/web.php`
- `tests/Feature/PolymarketAccountControllerTest.php`
- `tests/Feature/SettingsPolymarketTest.php`

### Sangat mungkin dihapus

- `app/Console/Commands/PolymarketSetCredentialsCommand.php`
- `app/Services/Polymarket/PolymarketConfigService.php`
- test yang hanya memverifikasi global setting credentials

### Perlu dipastikan sebelum dihapus

- semua pemakaian `PolymarketConfigService` di runtime/order execution
- semua referensi `system_settings` key `polymarket.*`
- apakah `vault_key_ref` benar-benar dipakai atau hanya placeholder

## Definisi Selesai

Flow dianggap selesai bila kondisi berikut terpenuhi:

1. User tidak pernah lagi diminta mengisi `api_key`, `api_secret`, `api_passphrase`.
2. User cukup mengisi data dasar account dan sumber signer private key.
3. Tombol `Validate` menghasilkan atau me-derive L2 credential dari L1 auth.
4. Hasil credential langsung tersimpan ke `polymarket_accounts`.
5. Runtime trading hanya memakai account aktif dari DB account.
6. Tidak ada lagi fallback auth trading dari `system_settings`.
7. Signing L1 dan order signing sudah sesuai format signature Polymarket yang valid.

## Catatan Risiko

Risiko terbesar bukan di UI, tetapi di crypto signing:

- jika implementasi signature masih pseudo-signing, maka auth dan order flow akan tetap salah walaupun UX sudah rapi
- karena itu, pembenahan `SigningService` harus diperlakukan sebagai prioritas tertinggi

## Kesimpulan

Arah plan yang benar adalah:

- pertahankan `polymarket_accounts` sebagai source of truth,
- hapus flow global credential di `settings`,
- hilangkan input manual L2 credential,
- ubah `Validate` menjadi flow resmi `L1 -> create/derive L2 -> save -> verify`,
- audit dan perbaiki implementasi signing agar benar-benar sesuai doc Polymarket.
