@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Positions</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Monitoring lifecycle posisi: open, reduced, closed.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Market</th>
                        <th class="px-4 py-3">Side</th>
                        <th class="px-4 py-3">Entry</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($positions as $position)
                        <tr>
                            <td class="px-4 py-4 font-mono">{{ $position->market_id }}</td>
                            <td class="px-4 py-4">{{ $position->side }}</td>
                            <td class="px-4 py-4 font-mono">${{ number_format($position->entry_price, 4) }}</td>
                            <td class="px-4 py-4 font-mono">${{ number_format($position->size, 2) }}</td>
                            <td class="px-4 py-4">
                                <span class="badge {{ $position->status === 'open' ? 'badge-success' : 'badge-info' }}">{{ $position->status }}</span>
                            </td>
                            <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">{{ $position->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada data posisi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            {{ $positions->links() }}
        </div>
    </div>
</div>
@endsection
