# Polymarket Bot Tasklist

Dokumen ini menurunkan [2026-04-25-polymarket-bot-design.md](file:///e:/Project/polymarket/2026-04-25-polymarket-bot-design.md) menjadi tasklist implementasi yang terurut berdasarkan prioritas.

Prinsip penyusunan:

- item dibuat actionable,
- item diurutkan berdasarkan dependency teknis,
- item difokuskan pada pekerjaan yang paling membuka jalan ke trading riil,
- checklist dapat dipakai sebagai acuan eksekusi sprint.

## P0 - Wajib Diselesaikan Dulu
Tanpa blok ini, bot belum bisa dianggap siap menuju integrasi Polymarket yang riil.

### 1. Standarkan Identitas Market Polymarket
- [ ] Buat model dan migration `markets`
- [ ] Buat model dan migration `market_tokens`
- [ ] Tambahkan field `condition_id`, `slug`, `question`, `active`, `closed`, `end_date`, `minimum_tick_size`, `neg_risk`, dan `raw_payload` pada desain tabel market
- [ ] Tambahkan relasi `Market -> many MarketToken`
- [ ] Simpan `yes_token_id` dan `no_token_id` sebagai record token terpisah, bukan string bebas
- [ ] Audit semua pemakaian `market_id` string internal seperti `TRUMP_2028`
- [ ] Ganti alur internal agar keputusan trading memakai identifier resmi Polymarket: `condition_id` dan `token_id`
- [ ] Tentukan aturan backward compatibility untuk data lama yang masih memakai `market_id` internal

Definition of done:

- semua flow internal bisa merujuk ke market resmi Polymarket,
- token YES/NO tersimpan dengan benar,
- tidak ada modul inti yang lagi bergantung pada market string dummy.

### 2. Bangun Market Sync dari Gamma API
- [ ] Buat service `PolymarketGammaService`
- [ ] Implement endpoint fetch active events/markets dari `https://gamma-api.polymarket.com`
- [ ] Implement pagination `limit` dan `offset`
- [ ] Simpan hasil fetch ke tabel `markets` dan `market_tokens`
- [ ] Simpan `raw_payload` untuk debugging dan audit
- [ ] Buat job `SyncMarketsJob`
- [ ] Tambahkan command artisan untuk sync manual market
- [ ] Tambahkan scheduler untuk sync berkala market aktif
- [ ] Pastikan market yang closed/resolved ikut diperbarui statusnya
- [ ] Tambahkan logging sukses/gagal ke `execution_logs`

Definition of done:

- sistem dapat menarik market aktif resmi dari Gamma API,
- mapping condition/token tersimpan rapi di database,
- data market bisa di-refresh berkala tanpa duplikasi.

### 3. Siapkan Konfigurasi Integrasi Polymarket
- [ ] Tambahkan konfigurasi Polymarket ke `config/services.php`
- [ ] Tambahkan env key untuk host Gamma, CLOB, Data API, chain ID, WebSocket URL, signature type, funder address
- [ ] Tambahkan env key untuk `POLYMARKET_API_KEY`, `POLYMARKET_API_SECRET`, `POLYMARKET_API_PASSPHRASE`, dan `POLYMARKET_PRIVATE_KEY`
- [ ] Tambahkan helper/config wrapper agar semua service membaca endpoint dari config, bukan hardcoded
- [ ] Pisahkan config environment local, staging, dan production lewat env

Definition of done:

- semua endpoint dan credential Polymarket dibaca dari config,
- tidak ada host penting yang hardcoded di service trading.

### 4. Implement L1 dan L2 Authentication Resmi
- [ ] Buat service `PolymarketAuthService`
- [ ] Implement create API credentials via L1 auth
- [ ] Implement derive existing API credentials via L1 auth
- [ ] Implement generator header L1: `POLY_ADDRESS`, `POLY_SIGNATURE`, `POLY_TIMESTAMP`, `POLY_NONCE`
- [ ] Implement generator header L2: `POLY_ADDRESS`, `POLY_SIGNATURE`, `POLY_TIMESTAMP`, `POLY_API_KEY`, `POLY_PASSPHRASE`
- [ ] Implement HMAC-SHA256 request signing untuk L2
- [ ] Simpan hasil derive/create credential dengan aman sesuai strategi aplikasi
- [ ] Tambahkan command verifikasi auth agar bisa menguji create/derive credential secara terpisah
- [ ] Tambahkan test untuk pembentukan header dan signature

Definition of done:

- aplikasi bisa membuat atau menurunkan API credential Polymarket,
- request authenticated ke CLOB bisa lolos auth,
- sign header dapat diuji deterministik.

### 5. Bangun Trade Executor yang Benar-Benar Riil
- [ ] Refactor `TradeExecutorService` agar tidak lagi memakai nonce, gas, signature, dan tx hash mock
- [ ] Tentukan pendekatan executor: direct REST CLOB, hybrid REST + local signer, atau adapter internal
- [ ] Implement pembentukan payload order resmi Polymarket
- [ ] Pastikan executor memakai `token_id`, `price`, `size`, `side`, `tick_size`, dan `neg_risk` yang valid
- [ ] Tambahkan dukungan `signature_type`
- [ ] Tambahkan dukungan `funder_address`
- [ ] Implement request `POST /order` atau endpoint trading yang dipilih
- [ ] Simpan response order penuh ke database
- [ ] Tangani error auth, validation, cancel-only mode, dan throttling
- [ ] Pastikan `ExecuteTradeJob` hanya membuka `Position` jika order benar-benar diterima/tereksekusi sesuai status yang disepakati

Definition of done:

- order dapat dikirim ke Polymarket dengan payload resmi,
- status respons exchange terekam,
- posisi internal tidak lagi dibuka dari hasil mock.

### 6. Buat Sinkronisasi Status Order dan Fill
- [ ] Buat tabel `orders`
- [ ] Simpan `polymarket_order_id`, `client_order_id`, `token_id`, `condition_id`, `status`, `filled_size`, `price`, `raw_request`, dan `raw_response`
- [ ] Buat service `OrderSyncService`
- [ ] Implement sinkronisasi order via REST
- [ ] Rancang integrasi user WebSocket channel untuk update order lifecycle
- [ ] Petakan status internal: `live`, `matched`, `confirmed`, `partial_fill`, `cancelled`, `expired`, `rejected`
- [ ] Update posisi berdasarkan fill aktual, bukan asumsi post order
- [ ] Tambahkan reconciliation job berkala

Definition of done:

- sistem tahu beda antara order live, partial fill, matched, confirmed, dan gagal,
- posisi internal mencerminkan status fill yang sebenarnya.

### 7. Ganti Listener Mock dengan Sumber Wallet Activity Riil
- [ ] Putuskan sumber data wallet activity utama: Data API, CLOB/user feed, chain indexer, atau provider pihak ketiga
- [ ] Dokumentasikan tradeoff tiap sumber data
- [ ] Hapus `fetchRecentTrades()` berbasis random dari daemon
- [ ] Ganti parser webhook hardcoded dengan parser event sungguhan
- [ ] Tambahkan dedupe berbasis tx hash, log index, order id, atau fingerprint event
- [ ] Tambahkan validasi bahwa event benar-benar trade, bukan transfer biasa
- [ ] Pastikan event bisa dipetakan ke `condition_id`, `token_id`, side, price, size, timestamp
- [ ] Simpan payload mentah untuk audit

Definition of done:

- wallet listener menghasilkan canonical trade event riil,
- trade yang diproses bukan data dummy,
- sistem bisa menjelaskan asal setiap signal.

## P1 - Prioritas Tinggi Setelah P0
Blok ini memperkuat kualitas keputusan dan keamanan trading setelah koneksi riil tersedia.

### 8. Perkuat Risk Engine
- [ ] Refactor `RiskManagerService` agar exposure tidak lagi mock
- [ ] Hitung exposure berdasarkan posisi terbuka aktual
- [ ] Tambahkan daily realized loss tracking
- [ ] Tambahkan unrealized PnL tracking
- [ ] Tambahkan slippage check berbasis best bid/ask atau orderbook depth
- [ ] Tambahkan max exposure per market
- [ ] Tambahkan max exposure per category/tag market
- [ ] Tambahkan max daily loss
- [ ] Tambahkan kill switch global
- [ ] Tambahkan reason code yang jelas setiap kali risk menolak trade

Definition of done:

- risk check berbasis data posisi nyata,
- penolakan trade dapat diaudit,
- sistem punya batas kerugian harian dan batas eksposur yang tegas.

### 9. Perluas Struktur Position dan PnL
- [ ] Tambahkan field `condition_id`, `token_id`, `order_id`, `average_fill_price`, `filled_size`, `realized_pnl`, `unrealized_pnl`, `closed_at`, dan `exit_reason` ke `positions`
- [ ] Tentukan apakah perlu tabel `position_events`
- [ ] Tambahkan event log saat open, add, reduce, close
- [ ] Pastikan setiap perubahan posisi dapat ditelusuri ke order/fill terkait

Definition of done:

- posisi internal cukup kaya untuk akuntansi dan audit,
- perubahan ukuran posisi dapat direkonstruksi dengan jelas.

### 10. Tingkatkan Signal Normalization
- [ ] Ubah `avgWalletSize` dari nilai fixed menjadi metrik historis per wallet
- [ ] Tambahkan recency decay pada signal
- [ ] Tambahkan anti-double-counting per wallet-market-window
- [ ] Tambahkan aturan untuk flip trade cepat pada wallet yang sama
- [ ] Evaluasi apakah perlu bedakan signal entry vs exit
- [ ] Tambahkan test untuk edge case normalisasi

Definition of done:

- signal lebih stabil,
- spam trade atau noise wallet tidak mendistorsi agregasi.

### 11. Tingkatkan Wallet Scoring
- [ ] Tentukan formula baru untuk `wallets.weight`
- [ ] Tambahkan komponen ROI, win rate, recency, consistency, dan drawdown
- [ ] Buat job recalculation score berkala
- [ ] Simpan snapshot scoring ke tabel tersendiri
- [ ] Tambahkan dashboard kecil untuk melihat evolusi skor wallet

Definition of done:

- bobot wallet tidak lagi manual semata,
- perubahan score bisa diaudit dan dibandingkan dari waktu ke waktu.

### 12. Tingkatkan Aggregation Engine
- [ ] Tambahkan decay weighting berbasis usia signal
- [ ] Tambahkan minimum jumlah wallet unik sebelum sinyal dianggap valid
- [ ] Tambahkan wallet concentration guard agar satu wallet dominan tidak terlalu mempengaruhi
- [ ] Evaluasi pindah window agregasi ke Redis untuk performa real-time
- [ ] Tambahkan test terhadap skenario market burst

Definition of done:

- agregasi lebih tahan noise,
- output wallet signal lebih relevan untuk execution.

## P2 - Penting Untuk Ketahanan dan Kualitas Strategi

### 13. Bangun Feature Engineering untuk AI dan Fusion
- [ ] Buat `FeatureBuilderService`
- [ ] Ambil midpoint, spread, depth imbalance, recent trade direction, momentum, volume, dan volatility
- [ ] Tambahkan fitur jumlah wallet unik yang masuk pada window tertentu
- [ ] Tambahkan fitur concentration score
- [ ] Simpan snapshot feature untuk debugging keputusan

Definition of done:

- AI dan fusion tidak lagi memakai fitur dummy,
- setiap keputusan bisa dijelaskan dari feature snapshot yang dipakai.

### 14. Refactor LLM Predictor Menjadi Riil
- [ ] Tambahkan konfigurasi provider AI ke `config/services.php`
- [ ] Implement request ke provider LLM yang dipilih
- [ ] Buat schema output yang ketat: `probability`, `confidence`, `reasoning_summary`
- [ ] Tambahkan timeout, retry, dan fallback ke heuristic
- [ ] Catat latensi dan error rate predictor

Definition of done:

- `LlmPredictor` tidak lagi mengembalikan angka statis,
- jika provider gagal, sistem tetap aman kembali ke heuristic.

### 15. Kalibrasi Fusion Engine
- [ ] Ganti threshold statis jika hasil backtest menunjukkan perlu
- [ ] Uji beberapa kombinasi bobot wallet vs AI
- [ ] Tambahkan mode conservative, balanced, dan aggressive
- [ ] Simpan alasan keputusan fusion ke `execution_logs`

Definition of done:

- threshold dan bobot didukung data,
- keputusan fusion dapat diaudit.

### 16. Perkuat Position Manager
- [ ] Hubungkan exit rules ke data market resmi
- [ ] Implement rule untuk close/reduce berdasarkan wallet exit riil
- [ ] Implement rule untuk AI deterioration berbasis feature nyata
- [ ] Implement rule time decay berdasarkan `end_date` market
- [ ] Pastikan reduce/close memicu executor order keluar yang riil

Definition of done:

- manajemen posisi tidak lagi berbasis asumsi statis,
- reduce dan close benar-benar bisa dieksekusi.

## P3 - Observability, Operasional, dan Hardening

### 17. Lengkapi Observability
- [ ] Pastikan semua stage pipeline menulis ke `execution_logs`
- [ ] Tambahkan `execution_time_ms` pada setiap stage penting
- [ ] Tambahkan correlation id per pipeline event
- [ ] Tambahkan dashboard order lifecycle
- [ ] Tambahkan dashboard PnL
- [ ] Tambahkan dashboard risk rejection summary

Definition of done:

- masalah pipeline mudah dilacak,
- operator bisa melihat bottleneck dan error utama.

### 18. Tambahkan Monitoring Rate Limit dan Latency
- [ ] Catat response time untuk Gamma API, CLOB API, dan AI provider
- [ ] Catat HTTP status yang berhubungan dengan throttle
- [ ] Tambahkan backoff strategy
- [ ] Tambahkan alarm saat rate limit mulai sering terjadi

Definition of done:

- sistem punya visibilitas pada bottleneck API eksternal,
- retry dan backoff tidak dilakukan secara buta.

### 19. Perkuat Testing
- [ ] Tambahkan unit test untuk auth signing
- [ ] Tambahkan unit test untuk signal normalization
- [ ] Tambahkan unit test untuk wallet scoring
- [ ] Tambahkan unit test untuk fusion engine
- [ ] Tambahkan feature test untuk webhook ingestion
- [ ] Tambahkan feature test untuk market sync
- [ ] Tambahkan test untuk risk rejection dan happy path execution

Definition of done:

- modul paling kritis punya coverage yang cukup,
- regresi dasar bisa ditangkap sebelum deploy.

### 20. Perkuat Backtesting
- [ ] Ubah backtest agar memakai market metadata yang lebih realistis
- [ ] Tambahkan simulasi fee, slippage, dan partial fill
- [ ] Tambahkan metrik outcome: win rate, drawdown, expectancy, average hold time
- [ ] Tambahkan pembanding antar strategy mode

Definition of done:

- hasil backtest lebih dekat ke kondisi produksi,
- parameter fusion dan risk bisa dikalibrasi dari data.

### 21. Siapkan Operasional Runtime
- [ ] Tentukan command runtime final untuk daemon, queue worker, dan scheduler
- [ ] Pastikan Horizon digunakan sesuai kebutuhan workload
- [ ] Dokumentasikan urutan startup service lokal dan server
- [ ] Buat checklist deploy environment
- [ ] Tambahkan health check internal untuk dependency penting

Definition of done:

- sistem bisa dijalankan ulang secara konsisten,
- startup dan operasi harian tidak bergantung pada ingatan manual.

## P4 - Nice to Have Setelah Inti Stabil

### 22. Kembangkan Dashboard Operasional
- [ ] Tambahkan halaman order detail
- [ ] Tambahkan halaman market detail
- [ ] Tambahkan halaman wallet score history
- [ ] Tambahkan filter berdasarkan market status, wallet, dan risk outcome

### 23. Tambahkan Klasifikasi Strategi
- [ ] Pisahkan mode follow trend vs mean reversion jika relevan
- [ ] Tambahkan segmentasi wallet berdasarkan kategori market
- [ ] Tambahkan confidence regime per jenis event

### 24. Tambahkan Reconciliation yang Lebih Dalam
- [ ] Cocokkan posisi internal dengan data external secara berkala
- [ ] Tambahkan job pemulihan saat worker mati di tengah order lifecycle
- [ ] Tambahkan laporan mismatch posisi/order

## Urutan Eksekusi yang Disarankan
Jika dikerjakan bertahap, urutan paling aman adalah:

1. P0.1 Standarkan identitas market
2. P0.2 Bangun market sync Gamma API
3. P0.3 Siapkan konfigurasi integrasi
4. P0.4 Implement auth L1 dan L2
5. P0.5 Bangun trade executor riil
6. P0.6 Sinkronisasi order dan fill
7. P0.7 Ganti listener mock dengan sumber riil
8. P1.8 Perkuat risk engine
9. P1.9 Perluas position dan PnL
10. P1.10 sampai P2.16 untuk kualitas keputusan
11. P3 dan P4 untuk hardening dan scale

## Milestone yang Layak Dipakai

### Milestone A - Market Ready
- [ ] Market resmi tersinkron dari Gamma API
- [ ] Token YES/NO tersimpan benar
- [ ] Sistem tidak lagi bergantung pada market dummy

### Milestone B - Auth Ready
- [ ] L1/L2 auth berhasil
- [ ] Request authenticated ke CLOB lolos
- [ ] Credential flow bisa diuji mandiri

### Milestone C - Execution Ready
- [ ] Order riil bisa dipost
- [ ] Order status bisa disinkronkan
- [ ] Position internal mengikuti fill aktual

### Milestone D - Risk Ready
- [ ] Exposure dan loss limit aktif
- [ ] Slippage check aktif
- [ ] Kill switch aktif

### Milestone E - Strategy Ready
- [ ] Feature builder aktif
- [ ] Fusion terkalibrasi
- [ ] Backtest lebih realistis

### Milestone F - Ops Ready
- [ ] Observability lengkap
- [ ] Testing inti memadai
- [ ] Runtime dan deploy checklist siap
