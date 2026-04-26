# Flow Update - Polymarket Bot (Laravel 12)

## Objective
Merapikan flow sistem Polymarket bot agar secure, scalable, dan production-ready dengan ketentuan:
- L1 auth untuk wallet signing (private key tetap di backend/env/vault)
- L2 auth untuk API key (`apiKey`, `secret`, `passphrase`) terenkripsi di database
- Clean service architecture (MVC + Service Layer, tanpa repository pattern)

---

## Kondisi Saat Ini (Ringkas)
- L2 header signing sudah berjalan di `PolymarketAuthService`.
- Eksekusi order masih terlalu gemuk di `TradeExecutorService` (resolve market, build payload, submit API dalam satu tempat).
- Credential masih menggunakan `SystemSetting` dan sebelumnya sempat membuka jalur penyimpanan private key di DB.
- Halaman settings sudah mulai ada, tapi belum final untuk model multi-wallet account yang production-ready.

---

## Target Security Model (Wajib)

### 1) Private Key (L1)
- Tidak disimpan di DB.
- Tidak dikirim ke frontend.
- Hanya di backend melalui:
  - `.env` khusus server, atau
  - secret manager/vault.
- Sistem hanya menyimpan referensi alias key (`env_key_name` / `vault_key_ref`).

### 2) API Credential (L2)
- `api_key`, `api_secret`, `api_passphrase` disimpan di DB dalam kondisi terenkripsi (`Crypt::encryptString`).
- Hanya didecrypt saat runtime di backend service.
- Tidak ditampilkan penuh di UI (mask + status saja).

### 3) Signing
- Semua signing dilakukan di backend:
  - L1 signing untuk setup/generate API key.
  - L2 HMAC SHA256 untuk request API.
  - EIP-712 signing untuk order.

---

## Struktur Sistem Target

### Controller
- `PolymarketAccountController`
  - create/update account profile
  - validate credential
  - rotate/revoke credential
- `BotExecutionController` (atau dashboard action controller)
  - start/stop bot
  - manual trigger sync/execution

### Service
- `PolymarketService`
  - wrapper API call (Gamma/CLOB/Data)
  - retry-aware + rate-limit aware transport
- `SigningService`
  - L1 signature
  - L2 HMAC signature
  - EIP-712 order signature
- `PolymarketCredentialService`
  - load/decrypt/rotate L2 credentials
- `PolymarketAccountService`
  - setup account lifecycle
  - validation/revocation flow
- `OrderExecutionService`
  - compose payload, sign order, submit, persist result

### Model
- `PolymarketAccount` (baru)
  - `wallet_address`
  - `funder_address`
  - `signature_type`
  - `env_key_name` / `vault_key_ref`
  - `api_key` (encrypted)
  - `api_secret` (encrypted)
  - `api_passphrase` (encrypted)
  - `credential_status`
  - `last_validated_at`
  - `last_error_code`

### Job / Queue
- `GeneratePolymarketApiKeyJob`
- `ExecuteTradeJob`
- `SyncOpenOrdersJob`
- `RefreshPolymarketCredentialJob`
- `HandlePolymarketRateLimitJob`

---

## Flow Diagram (ASCII)

```text
[Frontend Admin / Scheduler / Bot Engine]
                 |
                 v
      [Controller / Job Dispatcher]
                 |
                 v
       [PolymarketAccountService]
                 |
      +----------+-----------+
      |                      |
      v                      v
[PolymarketAccount DB]   [Secret Resolver]
(L2 encrypted)           (.env / Vault)
      |                      |
      +----------+-----------+
                 v
           [SigningService]
      (L1 sign, L2 HMAC, EIP-712)
                 |
                 v
          [PolymarketService]
                 |
                 v
           [Polymarket API]
                 |
                 v
        [Orders / Logs / Metrics]
```

---

## Flow Step-by-Step

### A. Setup Awal
1. Admin membuat `PolymarketAccount` (wallet, funder, signature type, env key alias).
2. Backend resolve private key dari env/vault berdasarkan alias.
3. `SigningService` menghasilkan L1 signature.
4. `PolymarketService` melakukan generate API credential.
5. Backend menyimpan `apiKey`, `secret`, `passphrase` ke DB (encrypted).
6. Sistem validasi credential baru ke endpoint CLOB.
7. Status account menjadi `active` jika valid.

### B. Runtime Bot
1. Job memilih account `active`.
2. `PolymarketCredentialService` decrypt credential L2.
3. Ambil server timestamp Polymarket.
4. `SigningService` generate HMAC signature (`timestamp + method + path + body`).
5. Request dikirim dengan header L2.
6. Response dicatat ke orders/logs/metrics.

### C. Order Execution
1. Signal engine menghasilkan order intent.
2. `OrderExecutionService` build order payload canonical.
3. Resolve private key dari env/vault.
4. `SigningService` sign EIP-712 order payload.
5. Submit order ke Polymarket API.
6. Persist status (`submitted/failed/filled/cancelled`) + raw metadata aman.

---

## Frontend Update Plan (Wajib Sinkron)

### Halaman yang perlu ada
- `Settings > Polymarket Accounts`
- `Accounts List` (multi-wallet)
- `Account Detail` (status credential, health, last validation)

### Aksi frontend
- Create account profile (`wallet_address`, `funder_address`, `signature_type`, `env_key_name`).
- Trigger "Generate/Rotate L2 Credential".
- Trigger "Validate Credential".
- Trigger "Revoke Credential".
- Toggle "Disable Trading" (kill switch).

### Aturan tampilan data sensitif
- Jangan tampilkan private key.
- Jangan tampilkan full secret/passphrase.
- Tampilkan status saja: `tersimpan`, `belum ada`, `error`, `revoked`.
- Mask `api_key` (contoh: `pk_****ABCD`).

### UX Status Badge
- `Active` (hijau)
- `Needs Rotation` (kuning)
- `Revoked` (merah)
- `Validation Failed` (merah)

---

## Edge Cases & Risk Handling

### Invalid Signature
- Tandai event `auth_failed`.
- Simpan metadata request (tanpa secret).
- Retry hanya untuk kasus canonicalization bug/transient issue.

### Timestamp Mismatch
- Selalu pakai server time endpoint.
- Terapkan drift tolerance dan fallback policy.
- Raise alert jika mismatch berulang.

### API Key Revoked
- Deteksi dari 401/403 berulang.
- Set `credential_status = revoked`.
- Stop semua execution job untuk account tersebut.

### Rate Limit
- Centralized retry policy + exponential backoff.
- Pisahkan throughput antara market-data dan trading endpoint.
- Tambahkan queue throttling per account.

### Wallet Compromise
- Kill switch account (disable trading instan).
- Revoke credential dan rotate alias key.
- Audit seluruh order setelah timestamp kompromi.

---

## Pseudocode Laravel

```php
final class PolymarketAccountService
{
    public function setupAccount(PolymarketAccount $account): void
    {
        $privateKey = $this->secretResolver->resolve($account->env_key_name);

        $l1Payload = $this->signingService->buildL1AuthPayload(
            address: $account->wallet_address,
            privateKey: $privateKey,
        );

        $credential = $this->polymarketService->generateApiCredential($l1Payload);

        $account->update([
            'api_key' => Crypt::encryptString($credential['apiKey']),
            'api_secret' => Crypt::encryptString($credential['secret']),
            'api_passphrase' => Crypt::encryptString($credential['passphrase']),
            'credential_status' => 'active',
            'last_validated_at' => now(),
        ]);
    }
}
```

```php
final class OrderExecutionService
{
    public function execute(PolymarketAccount $account, array $intent): array
    {
        $privateKey = $this->secretResolver->resolve($account->env_key_name);
        $credential = $this->credentialService->forAccount($account);

        $orderPayload = $this->polymarketService->buildOrderPayload($intent, $account);

        $signedOrder = $this->signingService->signEip712Order(
            payload: $orderPayload,
            privateKey: $privateKey,
        );

        return $this->polymarketService->submitOrder(
            account: $account,
            credential: $credential,
            signedOrder: $signedOrder,
        );
    }
}
```

```php
final class SigningService
{
    public function signL2Request(string $secret, string $timestamp, string $method, string $path, string $body): string
    {
        $decodedSecret = base64_decode($secret, true);

        if ($decodedSecret === false) {
            throw new RuntimeException('API secret tidak valid (base64).');
        }

        $signature = hash_hmac('sha256', $timestamp.$method.$path.$body, $decodedSecret, true);

        return base64_encode($signature);
    }
}
```

---

## Roadmap Eksekusi (Prioritas)

### P0 - Security Critical
- Hapus total jalur simpan `private_key` dari DB/UI/command.
- Tambah `SecretResolverService` (env/vault based).
- Batasi frontend agar hanya menampilkan status secret.

### P1 - Core Architecture
- Tambah model+tabel `polymarket_accounts`.
- Split service: `SigningService`, `PolymarketService`, `OrderExecutionService`, `PolymarketCredentialService`.
- Introduce account-based runtime (bukan global setting).

### P2 - Reliability
- Queue throttling, backoff, idempotency key.
- Health endpoint + alerting for auth/rate-limit failures.
- Audit log untuk rotate/revoke/validate.

### P3 - Scalability
- Multi-wallet scheduler policy.
- Account-level risk profile.
- Metrics dashboard per account.

---

## Definition of Done
- Private key tidak pernah tersimpan di DB dan tidak pernah tampil di frontend.
- L2 credential terenkripsi di DB, bisa rotate, validate, revoke.
- Order flow menggunakan EIP-712 signing terpusat di backend service.
- Frontend settings mendukung multi-account dan status credential health.
- Job runtime memiliki retry/backoff/idempotency serta logging yang bisa diaudit.
