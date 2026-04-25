@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 animate-fade-in-up">

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Balance Card -->
        <div class="card card-hover p-6 flex flex-col justify-between relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-brand-500/5 rounded-full group-hover:scale-150 transition-transform duration-700 ease-out"></div>
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Virtual Balance</p>
                    <h3 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 font-mono">$9,923.66</h3>
                </div>
                <div class="p-3 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 rounded-xl border border-brand-100 dark:border-brand-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="relative z-10 mt-6 flex items-center text-sm">
                <span class="text-red-500 font-semibold flex items-center bg-red-50 dark:bg-red-500/10 px-2 py-1 rounded-md">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                    0.76%
                </span>
                <span class="ml-3 text-gray-500 dark:text-gray-400">from initial $10k</span>
            </div>
        </div>

        <!-- Open Positions -->
        <div class="card card-hover p-6 flex flex-col justify-between relative overflow-hidden group delay-100">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-green-500/5 rounded-full group-hover:scale-150 transition-transform duration-700 ease-out"></div>
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Open Positions</p>
                    <h3 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 font-mono">2</h3>
                </div>
                <div class="p-3 bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-xl border border-green-100 dark:border-green-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
            </div>
            <div class="relative z-10 mt-6 flex items-center text-sm">
                <span class="text-gray-600 dark:text-gray-300">Exposure: <strong class="font-mono">$76.34</strong></span>
            </div>
        </div>

        <!-- Signals Processed -->
        <div class="card card-hover p-6 flex flex-col justify-between relative overflow-hidden group delay-200">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-purple-500/5 rounded-full group-hover:scale-150 transition-transform duration-700 ease-out"></div>
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Signals Aggregated</p>
                    <h3 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 font-mono">5</h3>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl border border-purple-100 dark:border-purple-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
            </div>
            <div class="relative z-10 mt-6 flex items-center text-sm">
                <span class="text-gray-600 dark:text-gray-300">Across <strong class="font-mono">2</strong> markets today</span>
            </div>
        </div>

        <!-- Tracked Wallets -->
        <div class="card card-hover p-6 flex flex-col justify-between relative overflow-hidden group delay-300">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-orange-500/5 rounded-full group-hover:scale-150 transition-transform duration-700 ease-out"></div>
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tracked Wallets</p>
                    <h3 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white mt-2 font-mono">3</h3>
                </div>
                <div class="p-3 bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl border border-orange-100 dark:border-orange-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
            <div class="relative z-10 mt-6 flex items-center text-sm">
                <span class="text-green-600 dark:text-green-400 font-semibold flex items-center bg-green-50 dark:bg-green-500/10 px-2 py-1 rounded-md">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    All active
                </span>
            </div>
        </div>
    </div>

    <!-- Main Grids -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in-up delay-200">
        
        <!-- Recent Executions (Takes 2 columns) -->
        <div class="lg:col-span-2 card flex flex-col overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-dark-border flex justify-between items-center bg-white dark:bg-dark-surface">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Recent Bot Executions</h2>
                <a href="#" class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition-colors">View All &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase tracking-wider bg-gray-50/50 dark:bg-dark-bg/50 border-b border-gray-100 dark:border-dark-border">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-semibold">Market ID</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Decision</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Size</th>
                            <th scope="col" class="px-6 py-4 font-semibold">Scores (W / AI / F)</th>
                            <th scope="col" class="px-6 py-4 font-semibold text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                        <tr class="hover:bg-brand-50/50 dark:hover:bg-dark-border/50 transition-colors group">
                            <td class="px-6 py-5 whitespace-nowrap font-semibold text-gray-900 dark:text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shadow-sm group-hover:scale-110 transition-transform">E</div>
                                    ETH_ETF_MAY
                                </div>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap">
                                <span class="badge badge-danger shadow-sm">BUY NO</span>
                                <span class="text-gray-500 dark:text-gray-400 text-xs ml-2 font-mono">@ 0.20</span>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap font-mono font-medium text-gray-700 dark:text-gray-300">$47.32</td>
                            <td class="px-6 py-5 whitespace-nowrap font-mono text-xs">
                                <span class="text-gray-500 bg-gray-100 dark:bg-dark-border px-1.5 py-0.5 rounded">0.30</span> / 
                                <span class="text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-500/10 px-1.5 py-0.5 rounded">0.09</span> / 
                                <span class="text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-500/10 px-1.5 py-0.5 rounded font-bold">0.24</span>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap text-right text-gray-500 dark:text-gray-400 font-medium">10 mins ago</td>
                        </tr>
                        <tr class="hover:bg-brand-50/50 dark:hover:bg-dark-border/50 transition-colors group">
                            <td class="px-6 py-5 whitespace-nowrap font-semibold text-gray-900 dark:text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shadow-sm group-hover:scale-110 transition-transform">E</div>
                                    ETH_ETF_MAY
                                </div>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap">
                                <span class="badge badge-danger shadow-sm">BUY NO</span>
                                <span class="text-gray-500 dark:text-gray-400 text-xs ml-2 font-mono">@ 0.21</span>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap font-mono font-medium text-gray-700 dark:text-gray-300">$29.02</td>
                            <td class="px-6 py-5 whitespace-nowrap font-mono text-xs">
                                <span class="text-gray-500 bg-gray-100 dark:bg-dark-border px-1.5 py-0.5 rounded">0.17</span> / 
                                <span class="text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-500/10 px-1.5 py-0.5 rounded">0.09</span> / 
                                <span class="text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-500/10 px-1.5 py-0.5 rounded font-bold">0.15</span>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap text-right text-gray-500 dark:text-gray-400 font-medium">15 mins ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Latest Signals (Takes 1 column) -->
        <div class="card flex flex-col overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-dark-border bg-white dark:bg-dark-surface">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-brand-500"></span>
                    </span>
                    Live Wallet Signals
                </h2>
            </div>
            <div class="p-6 flex-1 flex flex-col gap-6">
                
                <!-- Signal Item -->
                <div class="flex items-start gap-4 group">
                    <div class="mt-1">
                        <div class="w-8 h-8 rounded-full bg-green-50 dark:bg-green-500/10 border border-green-100 dark:border-green-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white font-mono">0xABC1...23</h4>
                            <span class="text-xs font-medium text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-500/10 px-2 py-0.5 rounded">Just now</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1.5">Bought <strong class="text-gray-900 dark:text-white font-mono bg-gray-100 dark:bg-dark-border px-1 rounded">1000 YES</strong> on <span class="font-mono text-xs bg-gray-100 dark:bg-dark-border px-1.5 py-0.5 rounded text-gray-800 dark:text-gray-200">TRUMP_2028</span></p>
                        <div class="w-full bg-gray-100 dark:bg-dark-border rounded-full h-2 mt-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-green-400 to-green-500 h-full rounded-full transition-all duration-1000 ease-out" style="width: 85%"></div>
                        </div>
                        <div class="flex justify-between mt-1.5">
                            <p class="text-[10px] text-gray-400 font-semibold uppercase">Signal Strength</p>
                            <p class="text-[10px] text-gray-500 font-mono font-bold">Weight: 0.85</p>
                        </div>
                    </div>
                </div>

                <div class="w-full h-px bg-gray-100 dark:bg-dark-border"></div>

                <!-- Signal Item -->
                <div class="flex items-start gap-4 group">
                    <div class="mt-1">
                        <div class="w-8 h-8 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white font-mono">0xGHI7...89</h4>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">2m ago</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1.5">Bought <strong class="text-gray-900 dark:text-white font-mono bg-gray-100 dark:bg-dark-border px-1 rounded">200 NO</strong> on <span class="font-mono text-xs bg-gray-100 dark:bg-dark-border px-1.5 py-0.5 rounded text-gray-800 dark:text-gray-200">TRUMP_2028</span></p>
                        <div class="w-full bg-gray-100 dark:bg-dark-border rounded-full h-2 mt-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-400 to-red-500 h-full rounded-full transition-all duration-1000 ease-out" style="width: 60%"></div>
                        </div>
                        <div class="flex justify-between mt-1.5">
                            <p class="text-[10px] text-gray-400 font-semibold uppercase">Signal Strength</p>
                            <p class="text-[10px] text-gray-500 font-mono font-bold">Weight: 0.60</p>
                        </div>
                    </div>
                </div>

            </div>
            <div class="p-4 border-t border-gray-100 dark:border-dark-border bg-gray-50/50 dark:bg-dark-bg/50 rounded-b-2xl text-center">
                <button class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 flex items-center justify-center w-full gap-2 transition-colors">
                    <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Listening for more...
                </button>
            </div>
        </div>

    </div>

    <!-- AI Model Status -->
    <div class="card overflow-hidden animate-fade-in-up delay-300">
        <div class="absolute inset-0 bg-gradient-to-r from-brand-500/10 to-transparent pointer-events-none"></div>
        <div class="relative p-8 border-l-4 border-l-brand-500 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-100 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 flex items-center justify-center shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    AI Fusion Engine Status
                </h3>
                <p class="text-base text-gray-600 dark:text-gray-300 mt-3 leading-relaxed max-w-3xl">
                    Currently operating on <span class="font-mono text-sm font-bold bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300 px-2 py-1 rounded-md mx-1 border border-brand-200 dark:border-brand-500/30">heuristic</span> fallback strategy. The system is analyzing orderbook momentum and volume. LLM integration via OpenAI is configured and ready for activation.
                </p>
            </div>
            <button class="btn-primary whitespace-nowrap shadow-brand-500/20">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Configure Strategy
            </button>
        </div>
    </div>

</div>
@endsection