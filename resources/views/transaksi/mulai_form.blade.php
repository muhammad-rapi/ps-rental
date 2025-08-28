@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-xl mx-auto bg-white p-8 rounded-lg shadow-md">
            @if ($perangkat)
                <h1 class="text-2xl font-bold mb-6 text-center">Mulai Sesi Rental untuk {{ $perangkat->nama }}</h1>
            @else
                <h1 class="text-2xl font-bold mb-6 text-center text-red-600">Perangkat tidak ditemukan!</h1>
            @endif

            <form action="{{ route('transaksi.proses_mulai') }}" method="POST" class="space-y-6">
                @csrf

                @if ($perangkat)
                    <input type="hidden" name="perangkat_id" value="{{ $perangkat->id }}">
                @endif
                
                <div>
                    <label for="paket_id" class="block text-sm font-medium text-gray-700">Pilih Paket</label>
                    <select id="paket_id" name="paket_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">-- Pilih Paket --</option>
                        @foreach($pakets as $paket)
                            <option value="{{ $paket->id }}" {{ old('paket_id') == $paket->id ? 'selected' : '' }}>
                                {{ $paket->nama }} - {{ $paket->durasi }} menit - Rp {{ number_format($paket->harga, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    @error('paket_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ url()->previous() }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Mulai Sesi
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
