@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Runtime Configuration</h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">App Env</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['app_env'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Queue Connection</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['queue_connection'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Cache Store</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['cache_store'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Redis Client</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['redis_client'] ?? 'n/a' }}</p>
            </div>
        </div>
    </div>
    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Health Check</h2>
        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
            Redis Status:
            <span class="{{ $runtime['redis_reachable'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $runtime['redis_reachable'] ? 'reachable' : 'unavailable' }}
            </span>
        </p>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Pending Jobs: <span class="font-mono">{{ number_format($runtime['jobs_pending']) }}</span></p>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Failed Jobs: <span class="font-mono">{{ number_format($runtime['jobs_failed']) }}</span></p>
        @if (!empty($runtime['redis_error']))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-500/10 dark:border-red-500/20 p-4">
                <p class="text-sm text-red-700 dark:text-red-300">{{ $runtime['redis_error'] }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
