<?php

namespace App\Models;

use App\Services\AdbService;
use Illuminate\Database\Eloquent\Model;

class Perangkat extends Model
{
    protected $table = 'perangkats';

    protected $guarded = ['id'];

    public function pakets()
    {
        return $this->hasMany(Paket::class);
    }

    public function shutdown(): bool
    {
        if (!$this->alamat_ip || !$this->auto_shutdown) {
            return false;
        }

        $adbService = new AdbService();
        return $adbService->powerOff($this->alamat_ip, $this->adb_port);
    }

    /**
     * Sleep perangkat via ADB
     */
    public function sleep(): bool
    {
        if (!$this->alamat_ip || !$this->auto_shutdown) {
            return false;
        }

        $adbService = new AdbService();
        return $adbService->sleep($this->alamat_ip, $this->adb_port);
    }

    /**
     * Wake up perangkat via ADB
     */
    public function wakeUp(): bool
    {
        if (!$this->alamat_ip) {
            return false;
        }

        $adbService = new AdbService();
        return $adbService->wakeUp($this->alamat_ip, $this->adb_port);
    }

    /**
     * Reboot perangkat via ADB
     */
    public function reboot(): bool
    {
        if (!$this->alamat_ip || !$this->auto_shutdown) {
            return false;
        }

        $adbService = new AdbService();
        return $adbService->reboot($this->alamat_ip, $this->adb_port);
    }

    /**
     * Check if ADB connected
     */
    public function isAdbConnected(): bool
    {
        if (!$this->alamat_ip) {
            return false;
        }

        $adbService = new AdbService();
        return $adbService->isConnected($this->alamat_ip, $this->adb_port);
    }

    /**
     * Get device info via ADB
     */
    public function getDeviceInfo(): array
    {
        if (!$this->alamat_ip) {
            return [];
        }

        $adbService = new AdbService();
        return $adbService->getDeviceInfo($this->alamat_ip, $this->adb_port);
    }
}
