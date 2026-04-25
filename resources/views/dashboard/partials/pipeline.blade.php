<div class="card p-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Pipeline Flow</h2>
        <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Ingestion -> Signal -> Fusion -> Risk -> Execution</span>
    </div>
    <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Webhook</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['webhook']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Trade</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['trade']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Signal</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['signal']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Fusion</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['fusion']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Risk Passed</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['risk']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Execution</p>
            <p class="text-xl font-bold mt-2 font-mono text-gray-900 dark:text-white">{{ number_format($pipeline['execution']) }}</p>
        </div>
    </div>
</div>
