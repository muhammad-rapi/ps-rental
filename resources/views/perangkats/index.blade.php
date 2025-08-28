
@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Daftar Perangkat</h1>

        {{-- Tabel Perangkat --}}
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            No.
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Nama Perangkat
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Paket Aktif
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Sisa Waktu
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($perangkats as $perangkat)
                        <tr id="row-{{ $perangkat->id }}">
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                {{ $perangkat->nama }}
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <span id="status-{{ $perangkat->id }}" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Tersedia
                                </span>
                            </td>
                            <td id="paket-{{ $perangkat->id }}" class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                -
                            </td>
                            <td id="timer-{{ $perangkat->id }}" class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                -
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right flex justify-end space-x-2">
                                <a href="{{ route('transaksi.mulai_form', $perangkat->id) }}" id="btn-mulai-{{ $perangkat->id }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">Mulai</a>
                                
                                <button onclick="pauseSesi({{ $perangkat->id }})" id="btn-pause-{{ $perangkat->id }}" class="hidden inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-500 hover:bg-yellow-600">Jeda</button>
                                
                                <button onclick="resumeSesi({{ $perangkat->id }})" id="btn-resume-{{ $perangkat->id }}" class="hidden inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-500 hover:bg-green-600">Lanjutkan</button>

                                <button onclick="stopSesi({{ $perangkat->id }})" id="btn-stop-{{ $perangkat->id }}" class="hidden inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">Stop</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const perangkats = @json($perangkats);
        
        let activeTransactions = {};
        let timers = {};

        // Fungsi untuk mengonversi detik menjadi format HH:MM:SS
        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = Math.floor(seconds % 60);

            const pad = (num) => String(num).padStart(2, '0');
            return `${pad(hours)}:${pad(minutes)}:${pad(remainingSeconds)}`;
        }

        // Fungsi untuk memperbarui tampilan setiap detik
        function updateUI() {
            // Reset UI untuk semua perangkat terlebih dahulu
            perangkats.forEach(perangkat => {
                const perangkatId = perangkat.id;
                const statusSpan = document.getElementById(`status-${perangkatId}`);
                const paketTd = document.getElementById(`paket-${perangkatId}`);
                const timerTd = document.getElementById(`timer-${perangkatId}`);
                const btnMulai = document.getElementById(`btn-mulai-${perangkatId}`);
                const btnPause = document.getElementById(`btn-pause-${perangkatId}`);
                const btnResume = document.getElementById(`btn-resume-${perangkatId}`);
                const btnStop = document.getElementById(`btn-stop-${perangkatId}`);

                // Atur ulang ke status default
                statusSpan.textContent = 'Tersedia';
                statusSpan.classList.remove('bg-green-100', 'bg-yellow-100');
                statusSpan.classList.add('bg-gray-100', 'text-gray-800');
                paketTd.textContent = '-';
                timerTd.textContent = '-';
                btnMulai.style.display = 'inline-flex';
                btnPause.style.display = 'none';
                btnResume.style.display = 'none';
                btnStop.style.display = 'none';
            });
            
            // Kemudian, perbarui hanya untuk transaksi yang aktif
            Object.values(activeTransactions).forEach(transaksi => {
                const perangkatId = transaksi.perangkat_id;
                const statusSpan = document.getElementById(`status-${perangkatId}`);
                const paketTd = document.getElementById(`paket-${perangkatId}`);
                const timerTd = document.getElementById(`timer-${perangkatId}`);
                const btnMulai = document.getElementById(`btn-mulai-${perangkatId}`);
                const btnPause = document.getElementById(`btn-pause-${perangkatId}`);
                const btnResume = document.getElementById(`btn-resume-${perangkatId}`);
                const btnStop = document.getElementById(`btn-stop-${perangkatId}`);
                
                // Tampilkan nama paket
                paketTd.textContent = transaksi.nama_paket || 'Paket tidak ditemukan';

                if (transaksi.status === 'running') {
                    // Update timer berdasarkan waktu berakhir yang dikirim dari server
                    const remainingSeconds = Math.max(0, transaksi.waktu_berakhir - (Date.now() / 1000));
                    
                    if (remainingSeconds <= 0) {
                        // Waktu habis, picu aksi stop dan keluar dari loop
                        stopSesi(perangkatId);
                        return; 
                    }
                    
                    timerTd.textContent = formatTime(remainingSeconds);
                    statusSpan.textContent = 'Sedang Berjalan';
                    statusSpan.classList.remove('bg-gray-100', 'bg-yellow-100');
                    statusSpan.classList.add('bg-green-100', 'text-green-800');
                    
                    btnMulai.style.display = 'none';
                    btnPause.style.display = 'inline-flex';
                    btnResume.style.display = 'none';
                    btnStop.style.display = 'inline-flex';

                } else if (transaksi.status === 'paused') {
                    // Tampilkan sisa waktu yang dikirim dari server
                    timerTd.textContent = formatTime(transaksi.waktu_sisa_detik);
                    statusSpan.textContent = 'Dijeda';
                    statusSpan.classList.remove('bg-green-100', 'bg-gray-100');
                    statusSpan.classList.add('bg-yellow-100', 'text-yellow-800');

                    btnMulai.style.display = 'none';
                    btnPause.style.display = 'none';
                    btnResume.style.display = 'inline-flex';
                    btnStop.style.display = 'inline-flex';
                }
            });
        }
        
        // Fungsi untuk mengambil data transaksi aktif dari server
        async function fetchActiveTransactions() {
            try {
                const response = await fetch('/api/transaksi/active');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                
                // Ubah format data menjadi objek dengan ID perangkat sebagai kunci
                let newTransactions = {};
                data.forEach(transaksi => {
                    newTransactions[transaksi.perangkat_id] = transaksi;
                });
                activeTransactions = newTransactions;
                
                updateUI();
            } catch (error) {
                console.error('Error fetching active transactions:', error);
            }
        }
        
        // Fungsi untuk mengirim permintaan aksi ke server
        async function sendAction(perangkatId, action) {
            try {
                const response = await fetch(`/api/transaksi/${perangkatId}/${action}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });
                const result = await response.json();
                if (response.ok) {
                    console.log(`Action ${action} successful:`, result.message);
                    fetchActiveTransactions(); // Perbarui UI setelah aksi
                } else {
                    console.error(`Action ${action} failed:`, result.message);
                }
            } catch (error) {
                console.error('Error during API call:', error);
            }
        }
        
        // Fungsi untuk aksi-aksi
        function pauseSesi(perangkatId) {
            sendAction(perangkatId, 'pause');
        }

        function resumeSesi(perangkatId) {
            sendAction(perangkatId, 'resume');
        }

        function stopSesi(perangkatId) {
            sendAction(perangkatId, 'stop');
        }

        // Jalankan fetch pertama kali dan set interval untuk polling
        document.addEventListener('DOMContentLoaded', () => {
            fetchActiveTransactions();
            setInterval(fetchActiveTransactions, 5000); // Poll setiap 5 detik
        });
    </script>
@endsection
