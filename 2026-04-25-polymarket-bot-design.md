 lakukan# Polymarket Multi-Wallet Copy Trade + AI Hybrid Bot

## 1. Overview
A fully automated, event-driven Polymarket trading bot built entirely on Laravel 12. The system tracks specific highly-profitable wallets (Copy Trade), normalizes their trading signals, aggregates them, and fuses them with an AI prediction model (LLM API or Rule-based) to execute trades automatically on the Polygon blockchain using native PHP EIP-712 signing.

## 2. Technical Stack
- **Framework:** Laravel 12 (MVC + Service Pattern)
- **Language:** PHP 8.2+
- **Database:** PostgreSQL/MySQL (Relational data)
- **Queue/Cache:** Redis + Laravel Horizon
- **Blockchain Integration:** Native PHP Web3 libraries (e.g., `web3.php`, `simplito/elliptic-php`) for EIP-712 signing and RPC communication.

## 3. Core Architecture & Modules

### A. Data Ingestion (Wallet Listener)
- **Primary:** Artisan Daemon (`php artisan polymarket:listen`) polling Polygon RPC or Polymarket API continuously.
- **Secondary (Prepared):** Webhook Controller to receive push events from Moralis or Alchemy.
- **Action:** Dispatches `ProcessWalletTradeJob` when a tracked wallet trades.

### B. Signal Normalizer & Wallet Scoring
- **Location:** `SignalNormalizerService` & `WalletScoringService`
- **Logic:** 
  - Calculates `strength = min(size / avg_wallet_size, 1.0)`.
  - Determines `direction` (+1 for YES, -1 for NO).
  - Retrieves wallet weight (score) from Redis (fallback to DB).
  - Stores the normalized signal in Redis/DB.

### C. Signal Aggregator (Multi-Wallet Core)
- **Job:** `AggregateSignalJob`
- **Location:** `SignalAggregatorService`
- **Logic:** 
  - Fetches recent signals for the specific `market_id` within the last 60 seconds from Redis.
  - Aggregates: `wallet_score = SUM(direction * strength * wallet_weight)`.
  - Normalizes the final score using a Sigmoid function: `wallet_signal = sigmoid(wallet_score)`.

### D. AI Prediction Service
- **Location:** `AiPredictionService` (Strategy Pattern Interface)
- **Implementations:**
  - `LlmPredictor`: Calls external LLM APIs (OpenAI/Anthropic) analyzing market features (price, momentum, volume) to output `probability` and `confidence`.
  - `HeuristicPredictor`: Rule-based fallback if LLM is disabled.

### E. Fusion Engine (Decision Core)
- **Job:** `FusionDecisionJob`
- **Location:** `FusionEngineService`
- **Logic:** 
  - Calculates `ai_signal = probability * confidence`.
  - Adjusts dynamic weights: if `confidence > 0.7` (Ww=0.5, Wa=0.5), else (Ww=0.7, Wa=0.3).
  - Computes `final_score = (wallet_signal * Ww) + (ai_signal * Wa)`.
  - Makes a decision: `> 0.65` (BUY YES), `< 0.35` (BUY NO), else SKIP.

### F. Risk Manager & Position Sizing
- **Location:** `RiskManagerService` (Pipeline Pattern)
- **Validations:** Market exposure limits (10%), daily loss limits, wallet conflict resolution, and slippage checks.
- **Position Sizing:** `max_size = 2% balance`, `position_size = max_size * final_score`.

### G. Trade Executor
- **Job:** `ExecuteTradeJob`
- **Location:** `TradeExecutorService`
- **Logic:** 
  - Signs Polymarket CTF/Orderbook transactions natively in PHP using EIP-712.
  - Sends the signed transaction to Polygon RPC.
  - Handles nonce management, gas price adjustments, and retries.

### H. Position Manager
- **Job:** `MonitorPositionJob` (Scheduled via Cron/Horizon)
- **Location:** `PositionManagerService`
- **Logic:** Tracks open positions and triggers exits if 70% of tracked wallets exit, AI probability drops significantly, or time decay rules are met.

## 4. Database Schema (No Repository Pattern)
Models will interact directly with the DB via Eloquent ORM.

- `Wallet`: id, address, weight, win_rate, roi, last_active
- `WalletTrade`: id, wallet_id, market_id, side, price, size, timestamp
- `Signal`: id, market_id, direction, strength, wallet_id, created_at
- `Position`: id, market_id, side, entry_price, size, status

## 5. End-to-End Flow (Asynchronous, Non-blocking)
1. Daemon/Webhook detects a trade.
2. `ProcessWalletTradeJob` normalizes and scores.
3. `AggregateSignalJob` calculates the `wallet_signal`.
4. `FetchAIPredictionJob` retrieves AI probability.
5. `FusionDecisionJob` combines signals and triggers Risk Check.
6. `ExecuteTradeJob` signs and broadcasts the transaction.
7. `MonitorPositionJob` oversees the lifecycle of the position.

## 6. Performance Optimization
- **Redis Cache:** Used heavily for latest signals, wallet score cache, and debouncing redundant incoming trades.
- **Batch Processing:** Aggregation runs every 1-2 seconds (debounced) instead of instantly on every signal to prevent rate limits and improve decision accuracy.
