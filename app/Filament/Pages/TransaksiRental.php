<?php

namespace App\Filament\Pages;

use App\Models\Perangkat;
use App\Models\Paket;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
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

    protected static ?string $navigationIcon = "heroicon-o-play";
    protected static ?string $navigationLabel = "Rental PS";
    protected static string $view = "filament.pages.transaksi-rental";
    protected static ?string $title = "Transaksi Rental PlayStation";

    public $activeTransactions = [];
    public $timers = [];
    public ?int $startSessionPerangkatId = null;
    public array $data = []; // Add this line

    public function mount(): void
    {
        // Load active transactions on mount
        $this->loadActiveTransactions();
    }

    public function getForm(string $name = 'form'): ?Form
    {
        return $this->makeForm()
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Perangkat::query()->where("is_active", true))
            ->columns([
                TextColumn::make("nomor")
                    ->label("No.")
                    ->sortable()
                    ->searchable(),
                TextColumn::make("nama")
                    ->label("Nama Perangkat")
                    ->sortable()
                    ->searchable(),
                TextColumn::make("merk")
                    ->label("Merk")
                    ->sortable()
                    ->searchable(),
                TextColumn::make("status")
                    ->label("Status")
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)
                            ->firstWhere("perangkat_id", $record->id);
                        if ($activeTransaction) {
                            return match ($activeTransaction["status"]) {
                                "running" => "Sedang Berjalan",
                                "paused" => "Dijeda",
                                default => "Tersedia",
                            };
                        }
                        return "Tersedia";
                    })
                    ->color(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)
                            ->firstWhere("perangkat_id", $record->id);
                        if ($activeTransaction) {
                            return match ($activeTransaction["status"]) {
                                "running" => "success",
                                "paused" => "warning",
                                default => "gray",
                            };
                        }
                        return "gray";
                    }),
                TextColumn::make("paket_aktif")
                    ->label("Paket Aktif")
                    ->getStateUsing(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)->firstWhere(
                            "perangkat_id",
                            $record->id
                        );
                        return $activeTransaction ? $activeTransaction["nama_paket"] : "-";
                    }),
            ])
            ->actions([
                Action::make("mulai")
                    ->label("Mulai")
                    ->icon("heroicon-o-play")
                    ->color("success")
                    ->action(function ($record) {
                        $this->startInlineSessionForm($record->id);
                    })
                    ->visible(function ($record) {
                        return !collect($this->activeTransactions)->firstWhere(
                            "perangkat_id",
                            $record->id
                        ) && is_null($this->startSessionPerangkatId);
                    }),
                Action::make("pause")
                    ->label("Pause")
                    ->icon("heroicon-o-pause")
                    ->color("warning")
                    ->action(function ($record) {
                        $this->pauseSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)
                            ->firstWhere("perangkat_id", $record->id);
                        return $activeTransaction && $activeTransaction["status"] === "running";
                    }),
                Action::make("resume")
                    ->label("Resume")
                    ->icon("heroicon-o-play")
                    ->color("success")
                    ->action(function ($record) {
                        $this->resumeSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)
                            ->firstWhere("perangkat_id", $record->id);
                        return $activeTransaction && $activeTransaction["status"] === "paused";
                    }),
                Action::make("stop")
                    ->label("Stop")
                    ->icon("heroicon-o-stop")
                    ->color("danger")
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $this->stopSesi($record->id);
                    })
                    ->visible(function ($record) {
                        $activeTransaction = collect($this->activeTransactions)
                            ->firstWhere("perangkat_id", $record->id);
                        return $activeTransaction !== null;
                    }),
                Action::make("shutdown")
                    ->label("Matikan")
                    ->icon("heroicon-o-power")
                    ->color("danger")
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $perangkat = Perangkat::find($record->id);
                        if ($perangkat && $perangkat->alamat_ip) {
                            $result = $perangkat->shutdown();
                            if ($result) {
                                Notification::make()
                                    ->title("Perangkat Dimatikan")
                                    ->body(
                                        "Perangkat {$perangkat->nama_perangkat} berhasil dimatikan"
                                    )
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title("Gagal Matikan Perangkat")
                                    ->body(
                                        "Gagal mematikan perangkat {$perangkat->nama_perangkat}"
                                    )
                                    ->danger()
                                    ->send();
                                Log::error("Device Shutdown Error", [
                                    "ip" => $perangkat->alamat_ip,
                                ]);
                            }
                        }
                    })
                    ->visible(function ($record) {
                        return $record->alamat_ip && $record->auto_shutdown;
                    }),
                Action::make("wakeup")
                    ->label("Nyalakan")
                    ->icon("heroicon-o-bolt")
                    ->color("success")
                    ->action(function ($record) {
                        $perangkat = Perangkat::find($record->id);
                        if ($perangkat && $perangkat->alamat_ip) {
                            $result = $perangkat->wakeUp();
                            if ($result) {
                                Notification::make()
                                    ->title("Perangkat Dinyalakan")
                                    ->body(
                                        "Perangkat {$perangkat->nama_perangkat} berhasil dinyalakan"
                                    )
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title("Gagal Nyalakan Perangkat")
                                    ->body(
                                        "Gagal menyalakan perangkat {$perangkat->nama_perangkat}"
                                    )
                                    ->danger()
                                    ->send();
                            }
                        }
                    })
                    ->visible(function ($record) {
                        return $record->alamat_ip;
                    }),
            ])
            ->emptyStateHeading("Tidak ada perangkat yang tersedia")
            ->emptyStateDescription(
                "Belum ada perangkat PlayStation yang terdaftar atau semua sedang tidak aktif."
            )
            ->poll("1s"); // Refresh every second for timer
    }

    public function resumeSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(
            function ($transaction) use ($perangkatId) {
                return $transaction["perangkat_id"] === $perangkatId;
            }
        );
        if ($transactionIndex !== false) {
            $this->activeTransactions[$transactionIndex]["status"] = "running";
            if (isset($this->timers[$perangkatId])) {
                $this->timers[$perangkatId]["last_update"] = now();
            }
            $this->saveActiveTransactions();
            Notification::make()
                ->title("Sesi Dilanjutkan")
                ->body("Sesi rental telah dilanjutkan")
                ->success()
                ->send();
        }
    }

    public function stopSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(
            function ($transaction) use ($perangkatId) {
                return $transaction["perangkat_id"] === $perangkatId;
            }
        );
        if ($transactionIndex !== false) {
            $transaction = $this->activeTransactions[$transactionIndex];
            Transaksi::find($transaction["id"])->update([
                "status" => "completed",
                "waktu_selesai" => now(),
                "keterangan" => "Sesi rental selesai",
            ]);
            $perangkat = Perangkat::find($perangkatId);
            if ($perangkat && $perangkat->auto_shutdown) {
                $this->shutdownDevice($perangkat);
            }
            unset($this->activeTransactions[$transactionIndex]);
            $this->activeTransactions = array_values($this->activeTransactions);
            unset($this->timers[$perangkatId]);
            $this->saveActiveTransactions();
            Notification::make()
                ->title("Sesi Dihentikan")
                ->body("Sesi rental telah dihentikan dan perangkat dimatikan")
                ->danger()
                ->send();
        }
    }

    private function updateTimer($perangkatId): void
    {
        if (!isset($this->timers[$perangkatId])) {
            return;
        }
        $transaction = collect($this->activeTransactions)->firstWhere(
            "perangkat_id",
            $perangkatId
        );
        if ($transaction && $transaction["status"] === "running") {
            $this->timers[$perangkatId]["remaining"] = max(
                0,
                $this->timers[$perangkatId]["remaining"] - 1
            );
            if ($this->timers[$perangkatId]["remaining"] <= 0) {
                $perangkat = Perangkat::find($perangkatId);
                if ($perangkat && $perangkat->auto_shutdown) {
                    $this->shutdownDevice($perangkat);
                }
                $this->stopSesi($perangkatId);
                Notification::make()
                    ->title("Waktu Habis")
                    ->body(
                        "Waktu rental telah habis, perangkat otomatis dimatikan"
                    )
                    ->warning()
                    ->send();
            }
        }
    }

    private function shutdownDevice(Perangkat $perangkat): void
    {
        try {
            $result = $perangkat->shutdown();
            if ($result) {
                Log::info("Device Shutdown Success", [
                    "perangkat_id" => $perangkat->id,
                    "nama_perangkat" => $perangkat->nama_perangkat,
                    "alamat_ip" => $perangkat->alamat_ip,
                ]);
            } else {
                Log::warning("Device Shutdown Failed", [
                    "perangkat_id" => $perangkat->id,
                    "nama_perangkat" => $perangkat->nama_perangkat,
                    "alamat_ip" => $perangkat->alamat_ip,
                ]);
            }
        } catch (Exception $e) {
            Log::error("Device Shutdown Error", [
                "perangkat_id" => $perangkat->id,
                "nama_perangkat" => $perangkat->nama_perangkat,
                "alamat_ip" => $perangkat->alamat_ip,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function wakeUpDevice(Perangkat $perangkat): void
    {
        try {
            $result = $perangkat->wakeUp();
            if ($result) {
                Log::info("Device Wake Up Success", [
                    "perangkat_id" => $perangkat->id,
                    "nama_perangkat" => $perangkat->nama_perangkat,
                    "alamat_ip" => $perangkat->alamat_ip,
                ]);
            } else {
                Log::warning("Device Wake Up Failed", [
                    "perangkat_id" => $perangkat->id,
                    "nama_perangkat" => $perangkat->nama_perangkat,
                    "alamat_ip" => $perangkat->alamat_ip,
                ]);
            }
        } catch (Exception $e) {
            Log::error("Device Wake Up Error", [
                "perangkat_id" => $perangkat->id,
                "nama_perangkat" => $perangkat->nama_perangkat,
                "alamat_ip" => $perangkat->alamat_ip,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function loadActiveTransactions(): void
    {
        $this->activeTransactions = session("active_transactions", []);
        $this->timers = session("timers", []);
        foreach ($this->timers as $perangkatId => $timer) {
            $this->updateTimer($perangkatId);
        }
    }

    private function saveActiveTransactions(): void
    {
        session(["active_transactions" => $this->activeTransactions]);
        session(["timers" => $this->timers]);
    }

    public function refreshTimers(): void
    {
        foreach ($this->timers as $perangkatId => $timer) {
            $this->updateTimer($perangkatId);
        }
        $this->saveActiveTransactions();
    }

    public function debugActiveTransactions()
    {
        dd([
            "activeTransactions" => $this->activeTransactions,
            "timers" => $this->timers,
            "session_active" => session("active_transactions"),
            "session_timers" => session("timers"),
        ]);
    }

    public function mulaiSesi($perangkatId, $paketId): void
    {
        $paket = Paket::find($paketId);
        $perangkat = Perangkat::find($perangkatId);
        $transaksi = Transaksi::create([
            "perangkat_id" => $perangkatId,
            "status" => "running",
            "keterangan" => "Sesi rental dimulai",
            "total" => $paket->harga,
            "waktu_mulai" => now(),
            "waktu_berakhir" => now()->addMinutes($paket->durasi),
            "durasi_aktual_detik" => $paket->durasi * 60,
            "user_id" => Auth::id(),
        ]);
        TransaksiItem::create([
            "transaksi_id" => $transaksi->id,
            "item_type" => Paket::class,
            "item_id" => $paketId,
            "jumlah" => 1,
            "harga_satuan" => $paket->harga,
            "subtotal" => $paket->harga,
        ]);
        $this->activeTransactions[] = [
            "id" => $transaksi->id,
            "perangkat_id" => $perangkatId,
            "paket_id" => $paketId,
            "nama_paket" => $paket->nama_paket,
            "durasi" => $paket->durasi * 60,
            "started_at" => now(),
            "paused_duration" => 0,
            "status" => "running",
        ];
        $this->timers[$perangkatId] = [
            "remaining" => $paket->durasi * 60,
            "last_update" => now(),
        ];
        $this->saveActiveTransactions();
        Notification::make()
            ->title("Sesi Dimulai")
            ->body(
                "Sesi rental untuk {$paket->nama_paket} telah dimulai"
            )
            ->success()
            ->send();
    }

    public function pauseSesi($perangkatId): void
    {
        $transactionIndex = collect($this->activeTransactions)->search(
            function ($transaction) use ($perangkatId) {
                return $transaction["perangkat_id"] === $perangkatId;
            }
        );
        if ($transactionIndex !== false) {
            if (isset($this->timers[$perangkatId])) {
                $this->updateTimer($perangkatId);
            }
            $this->activeTransactions[$transactionIndex]["status"] = "paused";
            $this->activeTransactions[$transactionIndex]["waktu_jeda"] = now();
            $this->saveActiveTransactions();
            Notification::make()
                ->title("Sesi Dijeda")
                ->body("Sesi rental telah dijeda")
                ->warning()
                ->send();
        }
    }

    protected function getActions(): array
    {
        return [
            PageAction::make("refresh")
                ->label("Refresh")
                ->icon("heroicon-o-arrow-path")
                ->action(function () {
                    $this->refreshTimers();
                    Notification::make()
                        ->title("Data Diperbarui")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function startInlineSessionForm($perangkatId): void
    {
        $this->startSessionPerangkatId = $perangkatId;
        $this->data = []; // Reset the data property for a fresh start
        $this->form->fill();
    }

    public function cancelInlineForm(): void
    {
        $this->startSessionPerangkatId = null;
        $this->data = []; // Reset data
    }

    public function submitInlineForm(): void
    {
        $perangkatId = $this->startSessionPerangkatId;
        if (!$perangkatId) {
            return;
        }
        $this->mulaiSesi($perangkatId, $this->data['paket_id']);
        $this->startSessionPerangkatId = null;
        $this->data = []; // Reset data after submission
    }

   public function getFormSchema(): array
{
    return [
        Select::make('paket_id')
            ->label('Pilih Paket')
            ->options(function () {
                return Paket::where('status', true)
                    ->get()
                    ->mapWithKeys(function ($paket) {
                        return [
                            $paket->id => $paket->nama . ' - ' . $paket->durasi . ' menit - Rp ' . number_format($paket->harga, 0, ',', '.')
                        ];
                    });
            })
            ->required()
            ->live()
            ->afterStateUpdated(function (callable $set, $state) {
                if ($state) {
                    $paket = Paket::find($state);
                    if ($paket) {
                        $set('durasi_preview', $paket->durasi . ' menit');
                        $set('harga_preview', 'Rp ' . number_format($paket->harga, 0, ',', '.'));
                    }
                } else {
                    $set('durasi_preview', '');
                    $set('harga_preview', '');
                }
            }),
        // TextInput::make('durasi_preview')
        //     ->label('Durasi')
        //     ->disabled()
        //     ->dehydrated(false),
        // TextInput::make('harga_preview')
        //     ->label('Harga')
        //     ->disabled()
        //     ->dehydrated(false),
    ];
}
}
