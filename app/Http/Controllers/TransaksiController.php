<?php

namespace App\Http\Controllers;

use App\Models\Paket;
use App\Models\Perangkat;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransaksiController extends Controller
{
    /**
     * Menampilkan daftar perangkat dan transaksi aktif.
     * Metode ini akan dipanggil oleh rute /perangkat.
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $perangkats = Perangkat::all();
        return view('perangkat.index', compact('perangkats'));
    }

    /**
     * Menampilkan formulir untuk memulai sesi rental.
     * Metode ini akan dipanggil oleh rute /transaksi/mulai/{perangkat}.
     * @param \App\Models\Perangkat $perangkat
     * @return \Illuminate\View\View
     */
    public function mulaiForm(Perangkat $perangkat)
    {
        $pakets = Paket::where('status', true)->get();
        return view('transaksi.mulai_form', compact('perangkat', 'pakets'));
    }

    /**
     * Memproses pengiriman formulir untuk memulai sesi.
     * Metode ini akan dipanggil oleh rute /transaksi/proses-mulai (POST).
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function prosesMulai(Request $request)
    {
        $validatedData = $request->validate([
            'perangkat_id' => 'required|exists:perangkats,id',
            'paket_id' => 'required|exists:pakets,id',
        ]);

        $perangkat = Perangkat::findOrFail($validatedData['perangkat_id']);
        $paket = Paket::findOrFail($validatedData['paket_id']);
        $user_id = Auth::check() ? Auth::user()->id : null;

        try {
            // Buat transaksi baru di database
            $transaksi = Transaksi::create([
                'perangkat_id' => $perangkat->id,
                'status' => 'running',
                'keterangan' => 'Sesi rental dimulai',
                'total' => $paket->harga,
                'waktu_mulai' => now(),
                'waktu_berakhir' => now()->addMinutes($paket->durasi),
                'durasi_aktual_detik' => $paket->durasi * 60,
                'user_id' => $user_id,
            ]);

            // Tambahkan item transaksi
            TransaksiItem::create([
                'transaksi_id' => $transaksi->id,
                'item_type' => Paket::class,
                'item_id' => $paket->id,
                'jumlah' => 1,
                'harga_satuan' => $paket->harga,
                'subtotal' => $paket->harga,
            ]);

            // Perangkat harus dinyalakan saat sesi dimulai (jika fitur ini diaktifkan)
            // if ($perangkat->auto_shutdown) {
            //     $perangkat->wakeUp();
            // }

            return redirect()->route('perangkat.index')->with('success', 'Sesi rental telah dimulai.');
        } catch (Exception $e) {
            Log::error('Gagal memulai sesi rental: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memulai sesi rental. Silakan coba lagi.');
        }
    }

    //---------------------------------------------------------
    // API ENDPOINTS UNTUK AJAX CALLS DARI JAVASCRIPT
    //---------------------------------------------------------

    /**
     * Mendapatkan daftar transaksi yang sedang aktif dari database.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveTransactions()
    {
        // Memuat relasi berlapis: 'transaksiItems' dan di dalamnya 'item' (yang merupakan 'Paket')
        $transactions = Transaksi::with(['perangkat', 'transaksiItems.item'])
            ->where('status', 'running')
            ->orWhere('status', 'paused')
            ->get();

        $formattedTransactions = $transactions->map(function ($transaksi) {
            $waktu_sisa_detik = 0;

            // Konversi waktu_berakhir menjadi objek Carbon secara manual jika belum
            $waktuBerakhirCarbon = Carbon::parse($transaksi->waktu_berakhir);

            if ($transaksi->status == 'running') {
                $waktu_sisa_detik = $waktuBerakhirCarbon->diffInSeconds(now());
            } elseif ($transaksi->status == 'paused') {
                $waktu_sisa_detik = $transaksi->sisa_durasi_detik ?? 0;
            }

            // Akses nama paket melalui relasi berlapis
            $namaPaket = optional($transaksi->transaksiItems->first()->item)->nama;

            return [
                'id' => $transaksi->id,
                'perangkat_id' => $transaksi->perangkat_id,
                'nama_perangkat' => $transaksi->perangkat->nama,
                'paket_id' => optional($transaksi->transaksiItems->first()->item)->id,
                'nama_paket' => $namaPaket,
                'status' => $transaksi->status,
                'waktu_mulai' => Carbon::parse($transaksi->waktu_mulai)->timestamp,
                'waktu_berakhir' => $waktuBerakhirCarbon->timestamp,
                'waktu_sisa_detik' => $waktu_sisa_detik,
            ];
        });

        return response()->json($formattedTransactions);
    }

    /**
     * Menghentikan sesi rental.
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Perangkat $perangkat
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopSesi(Request $request, Perangkat $perangkat)
    {
        $transaksi = Transaksi::where('perangkat_id', $perangkat->id)
            ->whereIn('status', ['running', 'paused'])
            ->first();

        if (!$transaksi) {
            return response()->json(['message' => 'Sesi tidak ditemukan.'], 404);
        }

        try {

            // Panggil perintah ADB untuk mematikan perangkat
            if ($perangkat->auto_shutdown) {
                $perangkat->shutdown();
            }

            $transaksi->update([
                'status' => 'completed',
                'waktu_selesai' => now(),
                'keterangan' => 'Sesi rental selesai',
            ]);

            return response()->json(['message' => 'Sesi berhasil dihentikan.']);
        } catch (Exception $e) {
            Log::error('Gagal menghentikan sesi: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan saat menghentikan sesi.'], 500);
        }
    }

    /**
     * Menjeda sesi rental.
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Perangkat $perangkat
     * @return \Illuminate\Http\JsonResponse
     */
    public function pauseSesi(Request $request, Perangkat $perangkat)
    {
        $transaksi = Transaksi::where('perangkat_id', $perangkat->id)
            ->where('status', 'running')
            ->first();

        if (!$transaksi) {
            return response()->json(['message' => 'Sesi tidak ditemukan atau tidak sedang berjalan.'], 404);
        }

        // Hitung sisa waktu dan simpan di database
        $waktuBerakhirCarbon = Carbon::parse($transaksi->waktu_berakhir);
        $sisa_durasi_detik = $waktuBerakhirCarbon->diffInSeconds(now());
        $transaksi->update([
            'status' => 'paused',
            'sisa_durasi_detik' => $sisa_durasi_detik,
        ]);

        return response()->json(['message' => 'Sesi berhasil dijeda.']);
    }

    /**
     * Melanjutkan sesi rental.
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Perangkat $perangkat
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumeSesi(Request $request, Perangkat $perangkat)
    {
        $transaksi = Transaksi::where('perangkat_id', $perangkat->id)
            ->where('status', 'paused')
            ->first();

        if (!$transaksi) {
            return response()->json(['message' => 'Sesi tidak ditemukan atau tidak sedang dijeda.'], 404);
        }

        // Ambil sisa waktu dan perbarui waktu berakhir
        $sisa_durasi_detik = $transaksi->sisa_durasi_detik;
        $waktu_berakhir_baru = now()->addSeconds($sisa_durasi_detik);

        $transaksi->update([
            'status' => 'running',
            'waktu_berakhir' => $waktu_berakhir_baru,
            'sisa_durasi_detik' => null, // Reset sisa waktu
        ]);

        return response()->json(['message' => 'Sesi berhasil dilanjutkan.']);
    }
}