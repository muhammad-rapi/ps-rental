
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header dengan informasi ringkas --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-full">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Perangkat Tersedia</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ \App\Models\Perangkat::where('is_active', true)->count() - count($this->activeTransactions) }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-full">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H7a2 2 0 00-2 2v4a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sedang Digunakan</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ count(collect($this->activeTransactions)->where('status', 'running')) }}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dijeda</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ count(collect($this->activeTransactions)->where('status', 'paused')) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Timer Auto Refresh --}}
        <div 
            wire:poll.1s="refreshTimers" 
            class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4"
        >
            <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Timer otomatis diperbarui setiap detik
                </span>
            </div>
        </div>

        {{-- Tabel Perangkat --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            {{ $this->table }}
        </div>

        {{-- Sesi Aktif Detail --}}
        @if(count($this->activeTransactions) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Sesi Aktif ({{ count($this->activeTransactions) }})
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($this->activeTransactions as $transaction)
                        @php
                            $perangkat = \App\Models\Perangkat::find($transaction['perangkat_id']);
                            $remainingSeconds = $this->timers[$transaction['perangkat_id']]['remaining'] ?? 0;
                            $hours = floor($remainingSeconds / 3600);
                            $minutes = floor(($remainingSeconds % 3600) / 60);
                            $seconds = $remainingSeconds % 60;
                        @endphp
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $perangkat->nama }}
                                </h4>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    @if($transaction['status'] === 'running') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @elseif($transaction['status'] === 'paused') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @endif
                                ">
                                    {{ $transaction['status'] === 'running' ? 'Berjalan' : 'Dijeda' }}
                                </span>
                            </div>
                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Nomor:</strong> {{ $perangkat->nomor }}</p>
                                <p><strong>Paket:</strong> {{ $transaction['nama'] }}</p>
                                <p><strong>Sisa Waktu:</strong> 
                                    <span class="font-mono text-lg {{ $remainingSeconds < 300 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}">
                                        {{ sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

   
</x-filament-panels::page>