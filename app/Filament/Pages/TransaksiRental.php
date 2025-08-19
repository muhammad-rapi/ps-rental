<?php

namespace App\Filament\Pages;

use App\Models\Perangkat;
use App\Models\Paket;
use App\Models\Transaksi;
use App\Models\Produk;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Actions\Action as PageAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class TransaksiRental extends Page implements HasTable, HasForms, HasActions
{
    use InteractsWithTable;
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-play';
    protected static ?string $navigationLabel = 'Rental PS';
    protected static string $view = 'filament.pages.transaksi-rental';
    protected static ?string $title = 'Transaksi Rental PlayStation';

    public $activeTransactions = [];
    public $timers = [];
    private $lastTimerUpdate = null; // Track kapan terakhir timer diupdate

    public function mount(): void
    {
        $this->loadActiveTransactions();
        $this->lastTimerUpdate = now();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Perangkat::query()->where('is_active', true))
            ->columns([
                TextColumn::make('nomor')
                    ->label('No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nama')
                    ->label('Nama Perangkat')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('merk')
                    ->label('Merk')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        if ($activeTransaction) {
                            return match ($activeTransaction['status']) {
                                'running' => 'Sedang Berjalan',
                                'paused' => 'Dijeda',
                                default => 'Tersedia'
                            };
                        }
                        return 'Tersedia';
                    })
                    ->color(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        if ($activeTransaction) {
                            return match ($activeTransaction['status']) {
                                'running' => 'success',
                                'paused' => 'warning',
                                default => 'gray'
                            };
                        }
                        return 'gray';
                    }),
                TextColumn::make('durasi_tersisa')
                    ->label('Durasi Tersisa')
                    ->getStateUsing(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        if ($activeTransaction && isset($this->timers[$record->id])) {
                            $remainingSeconds = $this->timers[$record->id]['remaining'];
                            $hours = floor($remainingSeconds / 3600);
                            $minutes = floor(($remainingSeconds % 3600) / 60);
                            $seconds = $remainingSeconds % 60;
                            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                        }
                        return '-';
                    })
                    ->badge()
                    ->color('info'),
                TextColumn::make('paket_aktif')
                    ->label('Paket Aktif')
                    ->getStateUsing(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        return $activeTransaction ? $activeTransaction['nama'] : '-';
                    }),
            ])
            ->actions([
                Action::make('mulai')
                    ->label('Mulai')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->form([
                        Select::make('paket_id')
                            ->label('Pilih Paket')
                            ->options(function () {
                                return Paket::where('status', 1)
                                    ->pluck('nama', 'id');
                            })
                            ->required()
                            ->live() // Gunakan live() instead of reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $paket = Paket::find($state);
                                    if ($paket) {
                                        $set('durasi_preview', $paket->durasi . ' menit');
                                        $set('harga_preview', 'Rp ' . number_format($paket->harga, 0, ',', '.'));
                                    }
                                }
                            }),
                        TextInput::make('durasi_preview')
                            ->label('Durasi')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('harga_preview')
                            ->label('Harga')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->action(function ($record, array $data) {
                        $this->mulaiSesi($record->id, $data['paket_id']);
                    })
                    ->visible(function ($record) {
                        return !collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                    })
                    ->modalWidth(MaxWidth::Medium),

                Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->action(function ($record) {
                        $this->pauseSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        return $activeTransaction && $activeTransaction['status'] === 'running';
                    }),

                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function ($record) {
                        $this->resumeSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        return $activeTransaction && $activeTransaction['status'] === 'paused';
                    }),

                Action::make('stop')
                    ->label('Stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $this->stopSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $record->id);
                        return $activeTransaction !== null;
                    }),

                Action::make('tambah_produk')
                    ->label('Tambah Pesanan')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->form([
                        Select::make('product_id')
                            ->label('Pilih Produk')
                            ->options(Produk::where('stok', '>', 0)->pluck('nama', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if ($state) {
                                    $product = Produk::find($state);
                                    if ($product) {
                                        $jumlah = (int) ($get('jumlah') ?? 1);
                                        $subtotal = $product->harga * $jumlah;
                                        $set('harga_preview', 'Rp ' . number_format($product->harga, 0, ',', '.'));
                                        $set('subtotal_preview', 'Rp ' . number_format($subtotal, 0, ',', '.'));
                                    }
                                }
                            }),
                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $productId = $get('product_id');
                                if ($productId && $state) {
                                    $product = Produk::find($productId);
                                    if ($product) {
                                        $jumlah = (int) $state;
                                        $subtotal = $product->harga * $jumlah;
                                        $set('subtotal_preview', 'Rp ' . number_format($subtotal, 0, ',', '.'));
                                    }
                                }
                            }),
                        TextInput::make('harga_preview')
                            ->label('Harga Satuan')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('subtotal_preview')
                            ->label('Subtotal')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->action(function ($record, array $data) {
                        $this->tambahProdukKeTransaksi($record->id, $data['product_id'], $data['jumlah']);
                    })
                    ->modalWidth(MaxWidth::Medium),

                Action::make('shutdown')
                    ->label('Matikan')
                    ->icon('heroicon-o-power')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $perangkat = Perangkat::find($record->id);
                        if ($perangkat && $perangkat->alamat_ip) {
                            $result = $perangkat->shutdown();

                            if ($result) {
                                Notification::make()
                                    ->title('Perangkat Dimatikan')
                                    ->body("Perangkat {$perangkat->nama} berhasil dimatikan")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal Matikan Perangkat')
                                    ->body("Gagal mematikan perangkat {$perangkat->nama}")
                                    ->danger()
                                    ->send();
                            }
                        }
                    })
                    ->visible(function ($record) {
                        return $record->alamat_ip && $record->auto_shutdown;
                    }),

                Action::make('wakeup')
                    ->label('Nyalakan')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->action(function ($record) {
                        $perangkat = Perangkat::find($record->id);
                        if ($perangkat && $perangkat->alamat_ip) {
                            $result = $perangkat->wakeUp();

                            if ($result) {
                                Notification::make()
                                    ->title('Perangkat Dinyalakan')
                                    ->body("Perangkat {$perangkat->nama} berhasil dinyalakan")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal Nyalakan Perangkat')
                                    ->body("Gagal menyalakan perangkat {$perangkat->nama}")
                                    ->danger()
                                    ->send();
                            }
                        }
                    })
                    ->visible(function ($record) {
                        return $record->alamat_ip;
                    }),
            ])
            ->emptyStateHeading('Tidak ada perangkat yang tersedia')
            ->emptyStateDescription('Belum ada perangkat PlayStation yang terdaftar atau semua sedang tidak aktif.');
            // HAPUS ->poll('1s') dari sini
    }

    public function mulaiSesi($perangkatId, $paketId): void
    {
        $paket = Paket::find($paketId);
        $perangkat = Perangkat::find($perangkatId);

        if (!$paket || !$perangkat) {
            Notification::make()
                ->title('Error')
                ->body('Paket atau Perangkat tidak ditemukan.')
                ->danger()
                ->send();
            return;
        }

        if ($perangkat->alamat_ip) {
            $this->wakeUpDevice($perangkat);
        }

        $transaksi = Transaksi::create([
            'perangkat_id' => $perangkat->id,
            'status' => 'running',
            'keterangan' => 'Sesi rental PS dimulai',
            'total' => $paket->harga,
            'user_id' => Auth::id(),
            'waktu_mulai' => now(),
        ]);

        $transaksi->transaksiItems()->create([
            'item_type' => 'App\Models\Paket',
            'item_id' => $paket->id,
            'jumlah' => 1,
            'harga_satuan' => $paket->harga,
            'subtotal' => $paket->harga,
        ]);

        $this->activeTransactions[] = [
            'id' => $transaksi->id,
            'perangkat_id' => $perangkatId,
            'paket_id' => $paketId,
            'nama' => $paket->nama,
            'durasi' => $paket->durasi * 60,
            'started_at' => now(),
            'paused_duration' => 0,
            'status' => 'running',
        ];

        $this->timers[$perangkatId] = [
            'remaining' => $paket->durasi * 60,
            'last_update' => now(),
        ];

        $this->saveActiveTransactions();

        Notification::make()
            ->title('Sesi Dimulai')
            ->body("Sesi rental untuk {$paket->nama} pada perangkat {$perangkat->nama} telah dimulai.")
            ->success()
            ->send();
    }

    public function pauseSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(function ($transaction) use ($perangkatId) {
            return $transaction['perangkat_id'] === $perangkatId;
        });

        if ($transactionIndex !== false) {
            $transactionData = $this->activeTransactions[$transactionIndex];

            if (isset($this->timers[$perangkatId])) {
                $this->updateTimer($perangkatId);
            }

            $transaksi = Transaksi::find($transactionData['id']);
            if ($transaksi) {
                $transaksi->update([
                    'status' => 'paused',
                    'waktu_jeda' => now(),
                    'keterangan' => 'Sesi rental dijeda',
                ]);
            }

            $this->activeTransactions[$transactionIndex]['status'] = 'paused';
            $this->activeTransactions[$transactionIndex]['paused_at'] = now();

            $this->saveActiveTransactions();

            Notification::make()
                ->title('Sesi Dijeda')
                ->body('Sesi rental telah dijeda.')
                ->warning()
                ->send();
        }
    }

    public function resumeSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(function ($transaction) use ($perangkatId) {
            return $transaction['perangkat_id'] === $perangkatId;
        });

        if ($transactionIndex !== false) {
            $transactionData = $this->activeTransactions[$transactionIndex];

            $transaksi = Transaksi::find($transactionData['id']);
            if ($transaksi) {
                $transaksi->update([
                    'status' => 'running',
                    'waktu_jeda' => null,
                    'keterangan' => 'Sesi rental dilanjutkan',
                ]);
            }

            $this->activeTransactions[$transactionIndex]['status'] = 'running';

            if (isset($this->timers[$perangkatId])) {
                $this->timers[$perangkatId]['last_update'] = now();
            }

            $this->saveActiveTransactions();

            Notification::make()
                ->title('Sesi Dilanjutkan')
                ->body('Sesi rental telah dilanjutkan.')
                ->success()
                ->send();
        }
    }

    public function stopSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(function ($transaction) use ($perangkatId) {
            return $transaction['perangkat_id'] === $perangkatId;
        });

        if ($transactionIndex !== false) {
            $transactionData = $this->activeTransactions[$transactionIndex];

            $transaksi = Transaksi::find($transactionData['id']);
            if ($transaksi) {
                $waktuMulai = Carbon::parse($transaksi->waktu_mulai);
                $waktuBerakhir = now();
                $durasiAktualDetik = $waktuMulai->diffInSeconds($waktuBerakhir);

                $transaksi->update([
                    'status' => 'completed',
                    'keterangan' => 'Sesi rental selesai',
                    'waktu_berakhir' => $waktuBerakhir,
                    'durasi_aktual_detik' => $durasiAktualDetik,
                ]);
            }

            $perangkat = Perangkat::find($perangkatId);
            if ($perangkat && $perangkat->auto_shutdown) {
                $this->shutdownDevice($perangkat);
            }

            unset($this->activeTransactions[$transactionIndex]);
            $this->activeTransactions = array_values($this->activeTransactions);
            unset($this->timers[$perangkatId]);

            $this->saveActiveTransactions();

            Notification::make()
                ->title('Sesi Dihentikan')
                ->body('Sesi rental telah dihentikan.')
                ->danger()
                ->send();
        }
    }

    public function tambahProdukKeTransaksi($perangkatId, $productId, $jumlah): void
    {
        $product = Produk::find($productId);
        $perangkat = Perangkat::find($perangkatId);

        if (!$product || !$perangkat) {
            Notification::make()
                ->title('Error')
                ->body('Produk atau Perangkat tidak ditemukan.')
                ->danger()
                ->send();
            return;
        }

        if ($product->stok < $jumlah) {
            Notification::make()
                ->title('Stok Tidak Cukup')
                ->body("Stok {$product->nama} hanya {$product->stok}.")
                ->danger()
                ->send();
            return;
        }

        $subtotal = $product->harga * $jumlah;

        $activeRentalTransaction = Transaksi::where('perangkat_id', $perangkatId)
            ->whereIn('status', ['running', 'paused'])
            ->first();

        if ($activeRentalTransaction) {
            $activeRentalTransaction->transaksiItems()->create([
                'item_type' => Produk::class,
                'item_id' => $product->id,
                'jumlah' => $jumlah,
                'harga_satuan' => $product->harga,
                'subtotal' => $subtotal,
            ]);

            $activeRentalTransaction->total += $subtotal;
            $activeRentalTransaction->save();

            Notification::make()
                ->title('Produk Ditambahkan')
                ->body("{$jumlah}x {$product->nama} ditambahkan ke transaksi rental.")
                ->success()
                ->send();
        } else {
            $newProductTransaction = Transaksi::create([
                'transaksi_type' => Produk::class,
                'transaksi_id' => $product->id,
                'perangkat_id' => $perangkat->id,
                'status' => 'completed',
                'keterangan' => "Penjualan {$product->nama}",
                'total' => $subtotal,
                'user_id' => Auth::id(),
                'waktu_mulai' => now(),
                'waktu_berakhir' => now(),
                'durasi_aktual_detik' => 0,
            ]);

            $newProductTransaction->transaksiItems()->create([
                'item_type' => Produk::class,
                'item_id' => $product->id,
                'jumlah' => $jumlah,
                'harga_satuan' => $product->harga,
                'subtotal' => $subtotal,
            ]);

            Notification::make()
                ->title('Transaksi Produk Baru Dibuat')
                ->body("Transaksi baru untuk {$jumlah}x {$product->nama} telah dibuat.")
                ->success()
                ->send();
        }

        $product->stok -= $jumlah;
        $product->save();
    }

    // Optimized timer update dengan batasan waktu
    private function updateTimer($perangkatId): void
    {
        if (!isset($this->timers[$perangkatId])) {
            return;
        }

        $transaction = collect($this->activeTransactions)->firstWhere('perangkat_id', $perangkatId);

        if ($transaction && $transaction['status'] === 'running') {
            $this->timers[$perangkatId]['remaining'] = max(0, $this->timers[$perangkatId]['remaining'] - 1);

            if ($this->timers[$perangkatId]['remaining'] <= 0) {
                $perangkat = Perangkat::find($perangkatId);
                if ($perangkat && $perangkat->auto_shutdown) {
                    $this->shutdownDevice($perangkat);
                }

                $this->stopSesi($perangkatId);

                Notification::make()
                    ->title('Waktu Habis')
                    ->body('Waktu rental telah habis.')
                    ->warning()
                    ->send();
            }
        }
    }

    private function shutdownDevice(Perangkat $perangkat): void
    {
        try {
            $result = $perangkat->shutdown();
            Log::info('Device Shutdown', [
                'success' => $result,
                'perangkat_id' => $perangkat->id
            ]);
        } catch (Exception $e) {
            Log::error('Device Shutdown Error', [
                'perangkat_id' => $perangkat->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function wakeUpDevice(Perangkat $perangkat): void
    {
        try {
            $result = $perangkat->wakeUp();
            Log::info('Device Wake Up', [
                'success' => $result,
                'perangkat_id' => $perangkat->id
            ]);
        } catch (Exception $e) {
            Log::error('Device Wake Up Error', [
                'perangkat_id' => $perangkat->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function loadActiveTransactions(): void
    {
        $this->activeTransactions = session('active_transactions', []);
        $this->timers = session('timers', []);

        foreach ($this->timers as $perangkatId => $timer) {
            $this->updateTimer($perangkatId);
        }
    }

    // Optimized save dengan caching untuk menghindari write berlebihan
    private function saveActiveTransactions(): void
    {
        // Hanya save jika ada perubahan signifikan
        $currentState = [
            'active_transactions' => $this->activeTransactions,
            'timers' => $this->timers
        ];
        
        $sessionState = [
            'active_transactions' => session('active_transactions', []),
            'timers' => session('timers', [])
        ];

        if ($currentState !== $sessionState) {
            session($currentState);
        }
    }

    // Batasi refresh timer hanya ketika diperlukan
    public function refreshTimers(): void
    {
        // Cek apakah sudah 1 detik sejak update terakhir
        if ($this->lastTimerUpdate && $this->lastTimerUpdate->diffInSeconds(now()) < 1) {
            return; // Skip jika belum 1 detik
        }

        $hasChanges = false;
        
        foreach ($this->timers as $perangkatId => $timer) {
            $oldRemaining = $timer['remaining'];
            $this->updateTimer($perangkatId);
            
            if (isset($this->timers[$perangkatId]) && 
                $this->timers[$perangkatId]['remaining'] !== $oldRemaining) {
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->saveActiveTransactions();
        }
        
        $this->lastTimerUpdate = now();
    }

    protected function getActions(): array
    {
        return [
            PageAction::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->refreshTimers();
                }),
        ];
    }
}