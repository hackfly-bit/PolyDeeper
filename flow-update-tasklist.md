# Tasklist Implementasi - Flow Update Polymarket Bot

## Cara Pakai
- [ ] Kerjakan berurutan dari P0 ke P3.
- [ ] Setiap task wajib punya PR terpisah + test terarah.
- [ ] Jangan lanjut ke fase berikutnya sebelum gate fase saat ini lolos.

---

## P0 - Security Critical

### Backend Security Refactor
- [x] Hapus dukungan simpan `private_key` dari service config (`PolymarketConfigService` atau penggantinya).
- [x] Hapus field `private_key` dari command credentials (`polymarket:set-credentials`).
- [x] Hapus field `private_key` dari form settings UI dan endpoint update settings.
- [x] Tambah `SecretResolverService` untuk resolve private key dari `env_key_name` / `vault_key_ref`.
- [x] Tambah validasi error yang jelas saat alias key tidak ditemukan.

### Data Exposure Guard
- [x] Pastikan API/Blade tidak pernah mengirim private key.
- [x] Mask `api_key` di response/UI (`pk_****ABCD`).
- [x] Ubah UI secret menjadi status only (`tersimpan` / `belum ada` / `error`).

### P0 Gate (Wajib Lolos)
- [x] Grep codebase: tidak ada jalur persist `private_key` ke DB.
- [x] Semua test terkait credential lulus.
- [x] Verifikasi manual settings page: tidak ada field private key.

---

## P1 - Core Architecture

### Model & Migration
- [x] Buat migration `polymarket_accounts` dengan kolom:
- [x] `wallet_address`, `funder_address`, `signature_type`.
- [x] `env_key_name`/`vault_key_ref`.
- [x] `api_key`, `api_secret`, `api_passphrase` (encrypted payload).
- [x] `credential_status`, `last_validated_at`, `last_error_code`.
- [x] Buat model `PolymarketAccount` + cast yang sesuai.

### Service Split (Tanpa Repository Pattern)
- [x] Buat `SigningService` (L1, L2 HMAC, EIP-712).
- [x] Buat `PolymarketService` (transport API + retry/rate-limit policy).
- [x] Buat `PolymarketCredentialService` (load/decrypt/rotate/revoke).
- [x] Buat `PolymarketAccountService` (setup lifecycle account).
- [x] Pecah `TradeExecutorService` menjadi wrapper transisi menuju `OrderExecutionService`.

### Controller & Endpoint
- [x] Tambah `PolymarketAccountController` (create/update/validate/rotate/revoke).
- [x] Tambah endpoint kill switch account (`disable_trading`).
- [x] Tambah endpoint health credential account.

### P1 Gate (Wajib Lolos)
- [x] Flow setup account baru bisa generate + simpan L2 credential encrypted.
- [x] Runtime bisa load account aktif berdasarkan `credential_status`.
- [x] Test feature untuk create/rotate/revoke account lulus.

---

## P1 Frontend Sync (Wajib)

### Halaman
- [x] Buat menu `Settings > Polymarket Accounts`.
- [x] Buat `Accounts List` (multi-wallet).
- [x] Buat `Account Detail` (status credential + health + last validation).

### Aksi UI
- [x] Form create account: `wallet_address`, `funder_address`, `signature_type`, `env_key_name`.
- [x] Tombol `Generate/Rotate L2 Credential`.
- [x] Tombol `Validate Credential`.
- [x] Tombol `Revoke Credential`.
- [x] Toggle `Disable Trading` (kill switch).

### UX & Security UI
- [x] Badge status: `Active`, `Needs Rotation`, `Revoked`, `Validation Failed`.
- [x] Tampilkan pesan error aman (tanpa menampilkan secret mentah).
- [x] Pastikan semua endpoint frontend memakai CSRF + validasi backend.

### Frontend Gate (Wajib Lolos)
- [x] Tidak ada field private key di UI.
- [x] Tidak ada response network yang mengandung secret/passphrase mentah.
- [x] Semua aksi account berhasil dengan flash message jelas.

---

## P2 - Reliability

### Queue & Retry
- [x] Tambah `ExecuteTradeJob` berbasis account.
- [x] Tambah `SyncOpenOrdersJob` berbasis account.
- [x] Tambah throttling per account untuk endpoint trade.
- [x] Terapkan exponential backoff untuk 429/5xx.
- [x] Terapkan idempotency key untuk submit order.

### Runtime Health
- [x] Tambah metric auth failure per account.
- [x] Tambah metric rate-limit hit per account.
- [x] Tambah alert untuk 401/403 berulang (revoked candidate).
- [x] Tambah alert untuk timestamp mismatch berulang.

### P2 Gate (Wajib Lolos)
- [ ] Soak test queue tidak menimbulkan duplicate order karena retry.
- [ ] Alert muncul saat simulasi revoked/rate-limit.
- [ ] Order lifecycle (`submitted/failed/filled/cancelled`) konsisten.

---

## P3 - Scalability

### Multi-Wallet Orchestration
- [x] Scheduler memilih account aktif berbasis policy (priority/risk).
- [x] Tambah risk profile per account (max exposure, max order size, cooldown).
- [x] Tambah dashboard metrik per account (PnL, error rate, throughput).

### Operational Hardening
- [x] Tambah audit log untuk rotate/revoke/validate credential.
- [x] Tambah runbook insiden wallet compromise.
- [x] Tambah prosedur rotasi credential berkala.

### P3 Gate (Wajib Lolos)
- [x] Multi-wallet berjalan paralel tanpa credential leakage antar account.
- [x] Kill switch per account menghentikan execution instan.
- [x] Audit trail lengkap untuk semua aksi sensitif.

---

## Test Matrix Minimum
- [ ] Unit: decrypt credential sukses/gagal.
- [ ] Unit: invalid base64 secret untuk L2 signing.
- [x] Unit: secret resolver gagal saat alias tidak ada.
- [x] Feature: create account + generate credential.
- [x] Feature: rotate/revoke/validate credential.
- [x] Feature: settings/accounts UI tidak expose data sensitif.
- [ ] Integration: submit order dengan EIP-712 signing.
- [ ] Integration: retry/backoff/idempotency untuk 429/5xx.

---

## Definition of Done Akhir
- [x] Private key hanya berada di backend env/vault.
- [ ] L2 credential terenkripsi di DB dan bisa rotate/revoke/validate.
- [x] Flow order memakai backend signing terpusat (L1/L2/EIP-712).
- [x] Frontend mendukung multi-account management tanpa expose secret.
- [x] Queue runtime stabil, auditable, dan siap produksi.
