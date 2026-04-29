# Sistem Polymarket Bot — Alur End-to-End

## 1. DATA MASUK (Ingestion Layer)

```
EXTERNAL SOURCE
├── Moralis / Alchemy (Webhook)
│
│   POST /webhook  →  WebhookController@handle
│
│   Parsing payload:
│   ├── tx_hash
│   ├── logIndex
│   ├── address (wallet)
│   ├── conditionId
│   ├── side (YES/NO)
│   ├── price
│   └── size
│
│   Redis Dedup:
│   └── setnx("webhook_trade:" . sha1(txHash|logIndex), 1)
│       Expire 3600s → abaikan jika duplikat
│
│   ProcessWalletTradeJob::dispatch(tradeData)
```

---

## 2. TRADE PROCESSING (Queue Worker)

```
ProcessWalletTradeJob::handle()

Step 1 ──▶ Redis Dedup
            setnx("wallet_trade_dedupe:" . sha1(seed), 1)
            Expire 3600s → skip jika sudah ada

Step 2 ──▶ Wallet firstOrCreate
            address → Wallet::create(weight=0.5, win_rate=0, roi=0)

Step 3 ──▶ Market Sync / Fetch
            ├── Jika conditionId sudah ada di DB → pakai lokal
            └── Jika belum → Market::updateOrCreate(
                    condition_id: conditionId,
                    slug/title/question/description: dari tradeData
                )

Step 4 ──▶ WalletTrade::updateOrCreate()
            idempotent — insert atau update data trade
            Kolom: wallet_id, market_ref_id, market_id,
                   condition_id, token_id, side, price,
                   size, traded_at

Step 5 ──▶ SignalNormalizerService::normalize()
            direction = YES → +1 | NO → -1
            strength  = min(size / avgWalletSize, 1.0)
            → Signal::create()

Step 6 ──▶ Redis DEBOUNCE (2 detik per market)
            setnx("aggregator_lock:{marketId}", 1)
            Expire 2s → skip jika lock sudah ada

Step 7 ──▶ AggregateSignalJob::dispatch(marketId)
            delayed 2 detik
```

---

## 3. SIGNAL AGGREGATION

```
AggregateSignalJob::handle()

SignalAggregatorService::aggregate(marketId, 60 detik)

Ambil semua Signal untuk marketId dalam 60 detik terakhir
│
├── foreach Signal:
│   weight = WalletScoringService::getWalletWeight(wallet_id)
│   walletScore += direction × strength × weight
│
└── walletSignal = sigmoid(walletScore)
    → nilai 0 ~ 1

    FusionDecisionJob::dispatch(marketId, walletSignal)
```

---

## 4. FUSION DECISION ENGINE

```
FusionDecisionJob::handle()

├── AI Prediction
│   AiPredictorInterface::predict(marketId, features)
│   ├── probability  (0 ~ 1)
│   └── confidence  (0 ~ 1)
│
├── FusionEngineService::fuse(walletSignal, probability, confidence)
│   aiSignal = probability × confidence
│
│   if confidence > 0.7:
│       Wa = 0.5  (AI weight)
│       Ww = 0.5  (wallet weight)
│   else:
│       Wa = 0.3
│       Ww = 0.7
│
│   finalScore = (walletSignal × Ww) + (aiSignal × Wa)
│
│   if finalScore > 0.65  →  action = "BUY YES"
│   if finalScore < 0.35  →  action = "BUY NO"
│   else                   →  action = "SKIP"
│
├── RiskManagerService::validate()
│   1. Market exposure > 10%?        → reject
│   2. Daily loss > 10%?             → reject
│   3. abs(walletSignal) < 0.1?      → reject
│   4. Slippage check fails?         → reject
│
├── Jika PASS:
│   positionSize = 2% × balance × finalScore
│
└── ExecuteTradeJob::dispatch(
        marketId, side, positionSize, price, accountId
    )
```

---

## 5. TRADE EXECUTION (Polymarket CLOB)

```
ExecuteTradeJob::handle()

OrderExecutionService::execute()

Step 1 ──▶ Resolve Account
            PolymarketAccountOrchestratorService::pickActiveAccount()
            Throttle: Cache::add("trade-throttle:{accountId}", 1, cooldown)
            → reject jika throttled

Step 2 ──▶ Resolve token_id
            Market + side (YES/NO) → token_id yang sesuai

Step 3 ──▶ Idempotency Key
            sha1(accountId | conditionId | tokenId |
                 side | size | price | timestamp)
            → jika sudah ada, return hasil cached

Step 4 ──▶ Order::create()
            status = "submitting"

Step 5 ──▶ SigningService::signEip712Payload()
            resolvePrivateKey(env_key_name)
            → EIP-712 signature

Step 6 ──▶ PolymarketService::postOrder()
            POST https://clob.polymarket.com/order
            Headers: L1/L2 auth + signature

Step 7 ──▶ Response handling
            SUCCESS:
            ├── Order::update(status=submitted, polymarket_order_id, tx_hash)
            └── Position::create(status=open)

            FAILURE:
            ├── Order::update(status=failed)
            ├── AuditService::log() jika auth error (401/403)
            └── ExecutionLog stage=trade_execution_failed
```

---

## 6. DATABASE LAYER

### Model & Relasi

```
┌──────────────┐       ┌─────────────────┐       ┌──────────────┐
│   WALLET     │       │  WALLET_TRADE   │       │   SIGNAL     │
├──────────────┤       ├─────────────────┤       ├──────────────┤
│ id PK        │◀──────│ wallet_id FK    │       │ id PK        │
│ address      │       │ market_ref_id FK│◀──────│ wallet_id FK │
│ name         │       │ market_id      │       │ market_id    │
│ weight       │       │ condition_id   │       │ condition_id │
│ win_rate     │       │ token_id       │       │ token_id     │
│ roi          │       │ side           │       │ direction    │
│ last_active  │       │ price          │       │ strength     │
└──────────────┘       │ size           │       └──────────────┘
                       │ traded_at      │
                       └────────────────┘

┌──────────────┐       ┌─────────────────┐       ┌──────────────┐
│   MARKET     │       │  MARKET_TOKEN    │       │  POSITION    │
├──────────────┤       ├─────────────────┤       ├──────────────┤
│ id PK        │◀──────│ market_id FK    │       │ id PK        │
│ condition_id │       │ token_id PK     │       │ market_id FK │
│ slug         │       │ outcome         │       │ condition_id │
│ title        │       │ is_yes          │       │ token_id     │
│ question     │       └─────────────────┘       │ order_id FK  │
│ description  │                                │ side         │
│ category     │       ┌─────────────────┐       │ entry_price  │
│ active       │       │     ORDER        │       │ size         │
│ closed       │       ├─────────────────┤       │ status       │
│ end_date     │       │ id PK           │◀──────│ closed_at    │
│ raw_payload  │       │ condition_id    │       │ exit_reason  │
│ last_synced  │       │ token_id       │       └──────────────┘
└──────────────┘       │ side           │
                       │ price          │
                       │ size           │
                       │ filled_size    │
                       │ status         │
                       │ idempotency_key│
                       │ signature_type │
                       │ tx_hash        │
                       └────────────────┘

┌─────────────────────────────────────────────────────────┐
│                    EXECUTION_LOG                         │
├─────────────────────────────────────────────────────────┤
│ id PK                                                    │
│ stage                                                    │
│   • webhook_received                                     │
│   • trade_saved                                          │
│   • signal_normalized                                     │
│   • aggregation_debounced                                 │
│   • aggregation_dispatched                                │
│   • fusion_started                                        │
│   • fusion_decision                                      │
│   • risk_rejected                                        │
│   • risk_passed                                          │
│   • trade_execution_started                              │
│   • trade_executed                                       │
│   • trade_execution_failed                               │
│ market_id                                                │
│ wallet_address                                           │
│ action                                                   │
│ status (info / success / warning / error)                │
│ message                                                  │
│ context (JSON)                                           │
│ occurred_at                                              │
└─────────────────────────────────────────────────────────┘
```

---

## 7. OBSERVABILITY LAYER

```
Pipeline Stats (DashboardController@index)
├── webhook    = count(stage = "webhook_received")
├── trade      = count(stage = "trade_saved")
├── signal     = count(stage = "signal_normalized")
├── fusion     = count(stage = "fusion_decision")
├── risk       = count(stage = "risk_passed")
└── execution  = count(stage = "trade_executed")

Risk Alerts
└── ExecutionLog (stage = "risk_rejected") → ditampilkan di dashboard

Error Highlights
└── storage/logs/laravel.log
    (di-read & ditampilkan di dashboard)
```

---

## 8. VIEW / UI LAYER

### Controller & Halaman

```
DashboardController@index
├── Stats: tracked_wallets, trades_today, signals_1h,
│         open_positions, active_exposure,
│         queue_backlog, failed_jobs
├── Pipeline counts
├── Wallet Performance (top 10)
├── Risk Alerts (recent risk_rejected)
└── Error Highlights

LiveActivity (Livewire — refresh polling 5 detik)
├── Recent Signals (6 terbaru) + market question + wallet name
└── Recent Executions (6 terbaru) + stage + status

PositionController@index
└── Daftar posisi terbuka (paginated)

SignalController@index
└── Log sinyal lengkap (paginated)

HistoryController@index
├── Filter: type / status / wallet / date range / keyword
├── Signal Logs (paginated)
└── Execution Logs (paginated)

MarkerController@index
├── Filter: wallet_id / status (open/closed) / search judul
├── Market list: judul, kategori, status open/closed,
│                wallet names (aggregated), market URL
└── Detail modal: volume, time remaining, rules, context

WalletController@index
├── Daftar wallet + stats
├── Tambah / Edit / Hapus / Refresh
└── PolymarketWalletStatsService sync

SettingsController@index
├── Runtime status (env, queue, cache, redis)
├── Polymarket server health probes
│   (CLOB API, Gamma API, Data API)
├── Account selection
└── Polymarket Account Management
    ├── PolymarketAccountController (CRUD)
    ├── Validate credentials
    ├── Rotate / Revoke credentials
    └── Enable / Disable trading
```

---

## 9. ROUTE SUMMARY

```
GET  /                          → DashboardController@index     (Dashboard)
GET  /positions                 → PositionController@index    (Positions)
GET  /signals                   → SignalController@index      (Signals)
GET  /history                   → HistoryController@index     (History)
GET  /wallets                   → WalletController@index      (Wallets)
POST /wallets                   → WalletController@store
PUT  /wallets/{wallet}          → WalletController@update
POST /wallets/{wallet}/refresh  → WalletController@refresh
DELETE /wallets/{wallet}        → WalletController@destroy
GET  /markers                   → MarkerController@index      (Markers)
GET  /settings                  → SettingsController@index     (Settings)
POST /settings/polymarket/select-account
                               → SettingsController@selectPolymarketAccount

GET  /settings/polymarket/accounts
                               → PolymarketAccountController@index
POST /settings/polymarket/accounts
                               → PolymarketAccountController@store
GET  /settings/polymarket/accounts/{account}
                               → PolymarketAccountController@show
PUT  /settings/polymarket/accounts/{account}
                               → PolymarketAccountController@update
POST .../validate               → PolymarketAccountController@validateCredentials
POST .../rotate                → PolymarketAccountController@rotateCredentials
POST .../revoke                → PolymarketAccountController@revokeCredentials
POST .../disable-trading        → PolymarketAccountController@disableTrading
POST .../enable-trading         → PolymarketAccountController@enableTrading
GET  .../health                 → PolymarketAccountController@health

POST /webhook                   → WebhookController@handle
```

---

## 10. RINGKASAN ALIRAN END-TO-END

```
┌──────────────────────────────────────────────────────────────────────┐
│                                                                      │
│  1. WEBHOOK                                                          │
│     Moralis/Alchemy ──▶ /webhook ──▶ WebhookController@handle       │
│                         Redis dedup ──▶ ProcessWalletTradeJob::dispatch │
│                                                                      │
│  2. TRADE INGEST                                                     │
│     ProcessWalletTradeJob                                             │
│     ├── Wallet::firstOrCreate()                                      │
│     ├── Market sync (Gamma API / upsert)                             │
│     ├── WalletTrade::updateOrCreate()  ← idempotent                  │
│     ├── Signal::create()  (direction + strength)                     │
│     └── AggregateSignalJob::dispatch()  (debounced 2s)               │
│                                                                      │
│  3. AGGREGATION                                                      │
│     SignalAggregatorService                                           │
│     ├── Ambil signals 60 detik window                                │
│     ├── walletScore = Σ(direction × strength × weight)               │
│     └── sigmoid() → walletSignal (0~1)                               │
│                                                                      │
│  4. FUSION DECISION                                                  │
│     FusionDecisionJob                                                 │
│     ├── AI prediction (probability + confidence)                     │
│     ├── fuse() → finalScore                                          │
│     ├── RiskManager::validate()  (exposure, loss, slippage)          │
│     └── if pass → positionSize → ExecuteTradeJob::dispatch()         │
│                                                                      │
│  5. EXECUTION                                                        │
│     ExecuteTradeJob                                                   │
│     ├── Resolve account (throttle per account)                       │
│     ├── Resolve token_id                                             │
│     ├── Sign EIP-712 order                                           │
│     ├── POST /order ke Polymarket CLOB                              │
│     ├── Order::update() + Position::create(status=open)              │
│     └── ExecutionLog recorded every stage                            │
│                                                                      │
│  6. OBSERVABILITY                                                    │
│     ExecutionLog ──▶ Pipeline stats ──▶ Dashboard                   │
│                    ──▶ Risk alerts    ──▶ Dashboard                  │
│                    ──▶ Error log     ──▶ Dashboard                  │
│                                                                      │
│  7. UI                                                               │
│     Dashboard / LiveActivity (polling)                               │
│     Positions / Signals / History / Markers / Wallets / Settings     │
│     (membaca ExecutionLog, Signal, Position, WalletTrade, Market)    │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Catatan Debugging via ExecutionLog

Setiap tahap pipeline mencatat `stage` di tabel `execution_log`. Gunakan halaman **History** untuk melacak di tahap mana sebuah market berhenti:

| `stage` | Arti |
|---|---|
| `webhook_received` | Webhook masuk, belum diproses |
| `trade_saved` | Trade berhasil disimpan |
| `signal_normalized` | Signal berhasil dibuat |
| `aggregation_debounced` | Agregasi di-skip karena debounce |
| `aggregation_dispatched` | AggregateSignalJob berhasil di-dispatch |
| `fusion_started` | FusionDecisionJob mulai berjalan |
| `fusion_decision` | Skor final sudah dihitung |
| `risk_rejected` | Gagal validasi risiko |
| `risk_passed` | Risk OK, eksekusi akan dimulai |
| `trade_execution_started` | ExecuteTradeJob mulai |
| `trade_executed` | Trade berhasil dieksekusi di Polymarket |
| `trade_execution_failed` | Gagal eksekusi (cek kolom `message` & `context`) |
