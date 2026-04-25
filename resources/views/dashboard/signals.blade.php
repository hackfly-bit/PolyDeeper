@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Signals</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Signal wallet ter-normalisasi per market.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Market</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">Strength</th>
                        <th class="px-4 py-3">Wallet</th>
                        <th class="px-4 py-3 text-right">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($signals as $signal)
                        <tr>
                            <td class="px-4 py-4 font-mono">{{ $signal->market_id }}</td>
                            <td class="px-4 py-4">
                                <span class="badge {{ $signal->direction > 0 ? 'badge-success' : 'badge-danger' }}">{{ $signal->direction > 0 ? 'YES' : 'NO' }}</span>
                            </td>
                            <td class="px-4 py-4 font-mono">{{ number_format($signal->strength, 3) }}</td>
                            <td class="px-4 py-4 font-mono text-xs">{{ $signal->wallet?->address ?? '-' }}</td>
                            <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">{{ $signal->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data signal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            {{ $signals->links() }}
        </div>
    </div>
</div>
@endsection
