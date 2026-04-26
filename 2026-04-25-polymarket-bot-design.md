# Polymarket Multi-Wallet Copy Trade + AI Hybrid Bot

## 1. Ringkasan
Dokumen ini adalah versi desain yang sudah diperbarui berdasarkan:

- audit source code proyek Laravel saat ini,
- inspeksi database lokal,
- riset dokumentasi resmi Polymarket API,
- dan gap analysis antara desain awal dengan implementasi aktual.

Tujuan sistem tetap sama: membangun bot Polymarket berbasis Laravel 12 yang:

- memonitor wallet target,
- menormalkan sinyal trade mereka,
- mengagregasikan conviction lintas wallet,
- menggabungkan hasil tersebut dengan AI/rule-based predictor,
- lalu mengeksekusi keputusan trading secara otomatis dan terukur.

Namun, berdasarkan audit kode, sistem saat ini masih berada pada fase `foundation + simulated pipeline`, belum pada tahap `real Polymarket production execution`.

## 2. Status Proyek Saat Ini

### Snapshot Audit
- **Framework:** Laravel 12.57.0
- **PHP:** 8.4
- **Database lokal aktif:** SQLite
- **Queue stack:** Laravel Jobs + Horizon package sudah terpasang
- **Redis client:** `predis/predis` sudah terpasang
- **Dashboard UI:** sudah ada
- **Polymarket SDK resmi:** belum dipasang
- **Native PHP signing/EIP-712 riil:** belum selesai

### Data yang Sudah Ada di Database Lokal
Hasil inspeksi database lokal saat audit:

- `wallets`: 3 row
- `wallet_trades`: 5 row
- `signals`: 5 row
- `positions`: 0 row
- `execution_logs`: 0 row

Maknanya:

- pipeline ingest dan normalisasi sudah pernah diuji minimal secara lokal,
- sinyal sudah pernah tersimpan,
- tetapi belum ada bukti eksekusi posisi live yang berhasil,
- dan observability pipeline belum benar-benar menghasilkan jejak runtime konsisten.

## 3. Audit Implementasi Aktual

### A. Yang Sudah Benar-Benar Ada

#### Ingestion Layer
Komponen sudah tersedia:

- `app/Console/Commands/PolymarketDaemonCommand.php`
- `app/Http/Controllers/WebhookController.php`
- route `POST /api/webhooks/polymarket`

Status:

- daemon command sudah ada,
- webhook endpoint sudah ada,
- keduanya sudah bisa mendispatch `ProcessWalletTradeJob`.

Catatan penting:

- daemon masih memakai `fetchRecentTrades()` berbasis data acak/mock,
- webhook parser masih hardcoded ke market, side, price, dan size contoh,
- belum ada parser event on-chain Polymarket/CTF yang riil.

#### Core Queue Pipeline
Job yang sudah ada:

- `ProcessWalletTradeJob`
- `AggregateSignalJob`
- `FusionDecisionJob`
- `ExecuteTradeJob`
- `MonitorPositionJob`

Status:

- pipeline async sudah tersusun dengan baik,
- ada debounce aggregation memakai Redis lock,
- flow antar-job sudah jelas dan usable sebagai fondasi produksi.

#### Models dan Tabel
Model yang sudah ada:

- `Wallet`
- `WalletTrade`
- `Signal`
- `Position`
- `ExecutionLog`

Migrasi yang relevan juga sudah tersedia untuk semua tabel inti tersebut.

#### Dashboard dan Monitoring Dasar
Sudah ada:

- `DashboardController`
- halaman dashboard, positions, signals, wallets, settings
- komponen Livewire untuk runtime/activity monitor

Status:

- UI observability dan CRUD wallet sudah cukup matang untuk tahap fondasi,
- dashboard membaca statistik trade, signal, queue backlog, failed jobs, dan status Redis.

#### Backtesting Dasar
Sudah ada:

- `php artisan polymarket:backtest`
- `BacktestEngine`

Status:

- backtest engine sudah bisa memproses trade historis JSON,
- tetapi masih memakai predictor/risk/execution yang disederhanakan.

### B. Yang Sudah Parsial

#### Signal Normalization
Sudah berjalan:

- menyimpan `WalletTrade`,
- mengubah side menjadi direction (`YES = 1`, `NO = -1`),
- menghitung `strength = min(size / avgWalletSize, 1.0)`,
- menyimpan `Signal`.

Keterbatasan:

- `avgWalletSize` masih fixed default,
- belum berbasis profil historis wallet,
- belum ada normalisasi berdasarkan token liquidity, spread, atau market regime.

#### Wallet Scoring
Sudah ada `WalletScoringService` dengan cache weight.

Keterbatasan:

- bobot wallet saat ini praktis membaca kolom `wallets.weight`,
- belum ada engine recalculation periodik berbasis ROI real, sharpe-like stability, drawdown, recency, atau precision per kategori market.

#### Aggregation Engine
Sudah ada:

- query signal 60 detik terakhir,
- perhitungan `SUM(direction * strength * wallet_weight)`,
- normalisasi sigmoid.

Keterbatasan:

- saat ini membaca dari database, bukan Redis stream/window yang optimal,
- belum ada anti-double-counting untuk wallet yang spam trade di market sama,
- belum ada decay weighting berdasarkan recency intra-window.

#### Fusion Engine
Sudah ada:

- kombinasi `wallet_signal` dan `ai_signal`,
- dynamic weighting berbasis confidence,
- threshold keputusan `BUY YES`, `BUY NO`, atau `SKIP`.

Keterbatasan:

- threshold masih statis,
- belum dikalibrasi dengan backtest real,
- `ai_signal = probability * confidence` masih terlalu sederhana untuk production scoring.

### C. Yang Masih Mock / Belum Riil

#### AI Predictor
Sudah ada dua driver:

- `HeuristicPredictor`
- `LlmPredictor`

Kondisi saat ini:

- manager dan interface strategy pattern sudah benar,
- driver `heuristic` sudah benar-benar mengembalikan nilai dari fitur sederhana,
- `LlmPredictor` masih mock dan mengembalikan angka statis,
- belum ada konfigurasi service resmi di `config/services.php` untuk OpenAI/Anthropic/provider lain.

#### Risk Manager
`RiskManagerService` sudah ada, tetapi:

- market exposure masih mock,
- daily loss masih mock,
- slippage check masih mock,
- validasi conflict masih sangat sederhana.

Artinya risk engine saat ini baru kerangka, belum risk engine trading riil.

#### Trade Executor
`TradeExecutorService` saat ini masih mock pada bagian paling penting:

- nonce mock,
- gas price mock,
- typed data belum sesuai skema order Polymarket final,
- signature masih `0x...mock_signature...`,
- broadcast transaction masih return hash acak lokal.

Implikasi:

- `ExecuteTradeJob` bisa membuat row `positions`,
- tetapi itu belum membuktikan order benar-benar terkirim ke Polymarket/CLOB/Polygon.

#### Position Manager
`PositionManagerService` sudah ada, tetapi:

- exit signal by wallet masih mock,
- AI re-evaluation masih menggunakan fitur statis,
- time decay rule belum terhubung ke metadata event market Polymarket.

## 4. Kesimpulan Tahap Pengembangan
Tahap sistem saat ini paling tepat digambarkan sebagai:

`Stage 1.5 - Internal pipeline sudah hidup, external trading integration belum hidup`

Urutan maturity saat ini:

1. Database dan dashboard: sudah ada
2. Async job pipeline: sudah ada
3. Signal processing internal: sudah ada
4. Backtest dasar: sudah ada
5. AI abstraction: sudah ada
6. Real market data ingestion: belum selesai
7. Real Polymarket authentication: belum selesai
8. Real order placement/cancel/fill sync: belum selesai
9. Real portfolio/risk accounting: belum selesai
10. Production hardening: belum mulai serius

## 5. Arsitektur Target yang Direvisi

### Layer 1 - Market Discovery
Sumber utama:

- Gamma API untuk daftar market/event aktif
- CLOB market endpoints untuk orderbook/price/tick size
- WebSocket market channel untuk update real-time

Fungsi:

- cari market aktif,
- simpan metadata market,
- map `condition_id`, `token_id YES`, `token_id NO`,
- simpan `minimum_tick_size`, `neg_risk`, `end_date`, `active`, `closed`.

### Layer 2 - Wallet Activity Ingestion
Target:

- ingest trade wallet target dari sumber yang benar-benar riil,
- ubah raw fill/trade menjadi canonical internal trade event.

Pilihan implementasi:

- polling endpoint trade/user data,
- webhook dari provider chain indexer,
- atau parser event on-chain Polygon untuk kontrak terkait Polymarket.

### Layer 3 - Signal Engine
Komponen:

- `SignalNormalizerService`
- `WalletScoringService`
- `SignalAggregatorService`

Tambahan yang direkomendasikan:

- signal decay berbasis usia event,
- anti-duplication per wallet-market-window,
- classification antara maker vs taker jika data tersedia,
- confidence penalty untuk wallet yang terlalu sering flip posisi.

### Layer 4 - AI / Quant Layer
Komponen:

- heuristic predictor sebagai fallback,
- LLM predictor sebagai enhancer,
- idealnya ditambah feature-engineering deterministic.

Feature minimum yang layak:

- current midpoint,
- spread,
- depth imbalance,
- recent trade direction,
- realized volatility,
- momentum 1m/5m/15m,
- number of distinct tracked wallets entering,
- wallet concentration score.

### Layer 5 - Fusion and Decisioning
Komponen:

- fusion score,
- dynamic thresholds,
- risk override,
- execution readiness checks.

Prinsip:

- copy trading tidak boleh langsung mengikuti setiap wallet,
- AI tidak boleh berdiri sendiri tanpa market microstructure,
- final decision harus memerlukan agreement minimum antara orderflow dan market context.

### Layer 6 - Execution Layer
Target produksi:

- derive/create API credentials Polymarket,
- sign order locally,
- post order ke CLOB API,
- sinkronkan order status,
- tangani cancel/replace,
- tangani partial fill,
- update posisi dan PnL internal.

### Layer 7 - Monitoring and Control
Tambahan yang direkomendasikan:

- order lifecycle dashboard,
- PnL dashboard,
- drawdown monitor,
- API latency monitor,
- rate limit monitor,
- reconciliation job antara status internal vs status exchange.

## 6. Riset Mendalam: Polymarket API

### A. Komponen API Resmi Polymarket
Selama riset, dokumentasi resmi Polymarket memperlihatkan bahwa integrasi tidak hanya satu API.

Komponen utama:

- **Gamma API**: market discovery, events, tags, metadata market
- **CLOB API**: orderbook, prices, balances, orders, trades, authentication, posting order
- **Data API**: data posisi/trade historis tertentu
- **WebSocket**: market channel dan user channel untuk real-time updates

Base URL penting:

- `https://gamma-api.polymarket.com`
- `https://clob.polymarket.com`
- `https://data-api.polymarket.com`
- `wss://ws-subscriptions-clob.polymarket.com/ws/market`
- `wss://ws-subscriptions-clob.polymarket.com/ws/user`

### B. Authentication Model
Dokumentasi resmi menunjukkan model auth dua tingkat:

#### L1 Authentication
Dipakai untuk:

- membuktikan kepemilikan wallet,
- create API credentials,
- derive existing API credentials,
- sign order secara lokal.

Header penting pada L1 flow:

- `POLY_ADDRESS`
- `POLY_SIGNATURE`
- `POLY_TIMESTAMP`
- `POLY_NONCE`

L1 signature menggunakan EIP-712.

#### L2 Authentication
Dipakai untuk request authenticated ke CLOB API.

Credential hasil L1:

- `apiKey`
- `secret`
- `passphrase`

Header penting L2:

- `POLY_ADDRESS`
- `POLY_SIGNATURE`
- `POLY_TIMESTAMP`
- `POLY_API_KEY`
- `POLY_PASSPHRASE`

`POLY_SIGNATURE` pada L2 bukan EIP-712 order signature, melainkan HMAC-SHA256 request signature berbasis `secret`.

### C. Signature Type dan Funder
Dokumentasi resmi menyebut tiga signature type utama:

- `0 = EOA`
- `1 = POLY_PROXY`
- `2 = GNOSIS_SAFE`

Ini penting karena executor Laravel saat ini belum memiliki konsep:

- `signature_type`,
- `funder_address`,
- dan perbedaan flow antara EOA vs proxy wallet.

Padahal tiga hal tersebut menentukan cara client/order ditandatangani dan bagaimana dana/gas diasumsikan berada.

### D. Market Discovery Flow yang Direkomendasikan
Flow resmi yang paling aman untuk bot:

1. Ambil market aktif dari Gamma API
2. Ambil `clobTokenIds`
3. Simpan mapping:
   - market slug
   - condition id
   - yes token id
   - no token id
4. Ambil detail market untuk `minimum_tick_size` dan `neg_risk`
5. Baru buat order

Catatan penting:

- bot saat ini masih memakai `market_id` string internal seperti `TRUMP_2028`,
- sedangkan integrasi riil harus berpindah ke identifier resmi Polymarket seperti `condition_id` dan `token_id`.

### E. WebSocket Resmi
Dokumentasi WebSocket resmi menunjukkan:

- channel `market` untuk public market data,
- channel `user` untuk order/trade lifecycle milik user,
- heartbeat `PING` tiap 10 detik dibutuhkan untuk market/user channel.

Market channel dapat mengirim:

- book snapshot,
- price change,
- last trade,
- best bid/ask,
- new market,
- market resolved.

User channel dapat mengirim:

- update order,
- update trade lifecycle.

Artinya, arsitektur ideal bot seharusnya:

- tidak hanya polling,
- tetapi juga menjaga koneksi WebSocket untuk market data dan sinkronisasi order status.

### F. Rate Limit Penting
Beberapa batas resmi yang relevan untuk desain bot:

- Gamma API general: `4,000 req / 10s`
- Gamma `/events`: `500 req / 10s`
- Gamma `/markets`: `300 req / 10s`
- CLOB general: `9,000 req / 10s`
- CLOB `/book`: `1,500 req / 10s`
- CLOB `/price`: `1,500 req / 10s`
- CLOB ledger endpoints: `900 req / 10s`
- API key endpoints: `100 req / 10s`
- `POST /order`: burst tinggi, tetapi tetap ada sustained limit per 10 menit

Implikasi desain:

- polling mentah per wallet atau per market tanpa cache akan cepat tidak efisien,
- perlu batching, debounce, cache, dan event-driven sync.

## 7. Gap Analysis terhadap Proyek Saat Ini

### Gap 1 - Identitas Market Masih Internal
Saat ini sistem memakai `market_id` bebas.

Yang dibutuhkan produksi:

- `condition_id`
- `yes_token_id`
- `no_token_id`
- `slug`
- `end_date`
- `minimum_tick_size`
- `neg_risk`

### Gap 2 - Tidak Ada Tabel Metadata Market
Saat ini belum ada tabel market resmi.

Perlu ditambah minimal tabel:

- `markets`
- `market_tokens`
- opsional `market_snapshots`

### Gap 3 - Wallet Listener Belum Riil
Saat ini listener masih mock/random.

Perlu diputuskan sumber riil:

- wallet trade via Data/CLOB/user feed,
- indexed chain events,
- atau provider indexing pihak ketiga.

### Gap 4 - Order Placement Belum Mengikuti Auth Flow Resmi
Saat ini belum ada:

- create/derive API key flow,
- HMAC L2 signing,
- order payload resmi,
- order status reconciliation.

### Gap 5 - Position Model Belum Cukup
Tabel `positions` saat ini belum menyimpan:

- external order id,
- token id,
- condition id,
- tx hash,
- average fill price,
- filled size,
- realized pnl,
- unrealized pnl,
- closed_at,
- exit_reason.

### Gap 6 - ExecutionLog Belum Terpakai Penuh
Tabel `execution_logs` sudah ada, tetapi audit menunjukkan masih kosong.

Kemungkinan:

- pipeline belum benar-benar dijalankan end-to-end setelah logging ditambahkan,
- atau worker/daemon belum aktif saat pengujian terakhir.

## 8. Desain Database yang Direkomendasikan Berikutnya

### Existing Tables
- `wallets`
- `wallet_trades`
- `signals`
- `positions`
- `execution_logs`

### Tabel Baru yang Sangat Direkomendasikan

#### `markets`
- `id`
- `condition_id`
- `slug`
- `question`
- `description`
- `active`
- `closed`
- `end_date`
- `minimum_tick_size`
- `neg_risk`
- `raw_payload`
- timestamps

#### `market_tokens`
- `id`
- `market_id`
- `token_id`
- `outcome`
- `is_yes`
- timestamps

#### `orders`
- `id`
- `position_id`
- `market_id`
- `token_id`
- `side`
- `order_type`
- `price`
- `size`
- `filled_size`
- `status`
- `polymarket_order_id`
- `client_order_id`
- `signature_type`
- `funder_address`
- `tx_hash`
- `raw_request`
- `raw_response`
- timestamps

#### `wallet_score_snapshots`
- `id`
- `wallet_id`
- `score`
- `win_rate`
- `roi`
- `consistency_score`
- `recency_score`
- `computed_at`

## 9. Alur End-to-End Produksi yang Direvisi

1. `SyncMarketsJob` mengambil market aktif dari Gamma API
2. Sistem menyimpan mapping market dan token
3. Wallet listener menerima trade/fill wallet target dari sumber riil
4. Event dinormalisasi ke `wallet_trades`
5. `ProcessWalletTradeJob` membuat `signals`
6. `AggregateSignalJob` membentuk `wallet_signal`
7. `FeatureBuilderService` mengambil market microstructure dari CLOB/WebSocket cache
8. `FusionDecisionJob` menjalankan AI + fusion + risk
9. `ExecuteTradeJob` membuat signed order resmi dan mengirim ke CLOB
10. `OrderSyncJob` menyinkronkan status order via user channel / REST
11. `PositionManagerService` mengelola reduce/close
12. `ReconciliationJob` mencocokkan posisi internal dengan exchange state

## 10. Rekomendasi Urutan Implementasi

### Fase 1 - Jadikan Data Layer Riil
Prioritas:

- buat tabel `markets` dan `market_tokens`,
- bangun `SyncMarketsJob`,
- ganti `market_id` internal dengan identifier resmi Polymarket,
- simpan token YES/NO yang valid.

### Fase 2 - Jadikan Executor Riil
Prioritas:

- implement create/derive API credentials,
- implement HMAC L2 signing,
- implement order payload resmi CLOB,
- simpan external order id dan response exchange.

### Fase 3 - Jadikan Listener Riil
Prioritas:

- tentukan sumber wallet activity yang benar,
- hapus random mock dari daemon,
- ubah webhook parser menjadi parser event sungguhan,
- tambahkan dedupe berdasarkan tx hash/log index/order id.

### Fase 4 - Perkuat Risk Engine
Prioritas:

- hitung exposure dari posisi terbuka,
- simpan realized/unrealized PnL,
- slippage check dari best bid/ask/orderbook depth,
- hard stop per market/category/day.

### Fase 5 - Baru Perkuat AI
Prioritas:

- bangun feature engineering dulu,
- gunakan heuristic sebagai baseline,
- kalibrasi threshold via backtest,
- LLM dijadikan enhancer, bukan sumber keputusan tunggal.

## 11. Environment Variables yang Direkomendasikan
Berikut variabel yang seharusnya disiapkan pada tahap integrasi nyata:

- `POLYMARKET_HOST=https://clob.polymarket.com`
- `POLYMARKET_GAMMA_HOST=https://gamma-api.polymarket.com`
- `POLYMARKET_DATA_HOST=https://data-api.polymarket.com`
- `POLYMARKET_CHAIN_ID=137`
- `POLYMARKET_PRIVATE_KEY=`
- `POLYMARKET_FUNDER_ADDRESS=`
- `POLYMARKET_SIGNATURE_TYPE=0`
- `POLYMARKET_API_KEY=`
- `POLYMARKET_API_SECRET=`
- `POLYMARKET_API_PASSPHRASE=`
- `POLYMARKET_WS_MARKET_URL=wss://ws-subscriptions-clob.polymarket.com/ws/market`
- `POLYMARKET_WS_USER_URL=wss://ws-subscriptions-clob.polymarket.com/ws/user`
- `AI_PROVIDER=heuristic`
- `OPENAI_API_KEY=`
- `ANTHROPIC_API_KEY=`

## 12. Realita Teknis yang Perlu Dipegang

### Jangan Asumsikan Trade = On-Chain Transfer Sederhana
Polymarket memiliki beberapa lapisan:

- discovery data,
- orderbook/CLOB,
- settlement / inventory / proxy signing,
- dan user-specific order state.

Karena itu, membaca transaksi Polygon mentah saja sering tidak cukup untuk copy trade yang stabil.

### Copy Trading yang Aman Perlu Canonical Event
Sebelum bot follow wallet, sistem harus bisa memastikan:

- ini benar trade atau hanya transfer,
- outcome yang dibeli YES/NO mana,
- harga dan size sebenarnya berapa,
- trade tersebut pembukaan posisi atau exit,
- apakah wallet target sedang scaling in atau flipping.

### Order Lifecycle Wajib Disinkronkan
Bot tidak boleh menganggap `order posted = position open`.

Harus dibedakan:

- order live,
- partial fill,
- matched,
- confirmed,
- cancelled,
- expired,
- rejected.

## 13. Sumber Riset Resmi

### Dokumentasi Polymarket yang Dipakai
- Authentication: `https://docs.polymarket.com/api-reference/authentication`
- Rate Limits: `https://docs.polymarket.com/api-reference/rate-limits`
- Quickstart: `https://docs.polymarket.com/quickstart`
- WebSocket Overview: `https://docs.polymarket.com/market-data/websocket/overview`
- Fetching Markets Guide: `https://docs.polymarket.com/developers/gamma-markets-api/fetch-markets-guide`

### Temuan Inti dari Riset
- autentikasi trading Polymarket menggunakan model `L1 + L2`
- market discovery terbaik dimulai dari Gamma API
- order placement butuh token ID yang valid, tick size, neg risk, signature type, dan funder address
- WebSocket sangat penting untuk sinkronisasi market dan order lifecycle
- executor proyek saat ini belum memenuhi requirement auth dan request flow resmi tersebut

## 14. Ringkasan Final
Proyek ini **bukan kosong**. Fondasi internalnya sudah cukup bagus:

- pipeline async sudah ada,
- dashboard sudah ada,
- tabel inti sudah ada,
- backtest dasar sudah ada,
- abstraction AI sudah ada.

Tetapi proyek ini juga **belum live-ready** karena tiga pilar utama masih belum riil:

1. source wallet activity riil,
2. Polymarket authentication + order submission riil,
3. portfolio/risk reconciliation riil.

Kesimpulan paling jujur:

> Sistem sudah sampai tahap "simulasi pipeline bot trading yang meyakinkan", tetapi belum sampai tahap "bot Polymarket production-grade yang benar-benar bisa trading aman di mainnet".
