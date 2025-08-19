<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class AdbService
{
    /**
     * ADB executable path
     * Set this to the full path if ADB is not in system PATH
     */
    private string $adbPath;

    public function __construct()
    {
        // Check if ADB is in PATH, otherwise use full path
        $this->adbPath = $this->getAdbPath();
    }

    /**
     * Get ADB executable path
     */
    private function getAdbPath(): string
    {
        // Try to find ADB in system PATH first
        $result = Process::run('which adb');
        if ($result->successful() && !empty(trim($result->output()))) {
            return 'adb';
        }

        // Common ADB installation paths
        $commonPaths = [
            '/usr/bin/adb',
            '/usr/local/bin/adb',
            '/opt/android-sdk/platform-tools/adb',
            '/usr/local/android-sdk-platform-tools/adb',
            config('services.adb.path', 'adb'), // Allow configuration via config file
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // If not found, return 'adb' and let the system handle the error
        return 'adb';
    }

    /**
     * Check if ADB is available
     */
    public function isAdbAvailable(): bool
    {
        try {
            $result = Process::run($this->adbPath . ' version');
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Not Available', [
                'path' => $this->adbPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Connect to ADB device
     */
    public function connect(string $ip, int $port = 5555): bool
    {
        try {
            if (!$this->isAdbAvailable()) {
                Log::error('ADB Not Available', ['path' => $this->adbPath]);
                return false;
            }

            $command = "{$this->adbPath} connect {$ip}:{$port}";
            $result = Process::run($command);
            
            Log::info('ADB Connect', [
                'ip' => $ip,
                'port' => $port,
                'command' => $command,
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'success' => $result->successful()
            ]);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Connect Error', [
                'ip' => $ip,
                'port' => $port,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Disconnect from ADB device
     */
    public function disconnect(string $ip, int $port = 5555): bool
    {
        try {
            $command = "{$this->adbPath} disconnect {$ip}:{$port}";
            $result = Process::run($command);
            
            Log::info('ADB Disconnect', [
                'ip' => $ip,
                'port' => $port,
                'command' => $command,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Disconnect Error', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Power off device
     */
    public function powerOff(string $ip, int $port = 5555): bool
    {
        try {
            // Connect first
            if (!$this->connect($ip, $port)) {
                return false;
            }

            // Power off command
            $command = "{$this->adbPath} -s {$ip}:{$port} shell reboot -p";
            $result = Process::run($command);
            
            Log::info('ADB Power Off', [
                'ip' => $ip,
                'port' => $port,
                'command' => $command,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            // Disconnect after power off
            $this->disconnect($ip, $port);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Power Off Error', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reboot device
     */
    public function reboot(string $ip, int $port = 5555): bool
    {
        try {
            if (!$this->connect($ip, $port)) {
                return false;
            }

            $command = "{$this->adbPath} -s {$ip}:{$port} shell reboot";
            $result = Process::run($command);
            
            Log::info('ADB Reboot', [
                'ip' => $ip,
                'port' => $port,
                'command' => $command,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            $this->disconnect($ip, $port);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Reboot Error', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Kill specific app
     */
    public function killApp(string $ip, string $packageName, int $port = 5555): bool
    {
        try {
            if (!$this->connect($ip, $port)) {
                return false;
            }

            $command = "{$this->adbPath} -s {$ip}:{$port} shell am force-stop {$packageName}";
            $result = Process::run($command);
            
            Log::info('ADB Kill App', [
                'ip' => $ip,
                'port' => $port,
                'package' => $packageName,
                'command' => $command,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Kill App Error', [
                'ip' => $ip,
                'package' => $packageName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send key event (like pressing power button)
     */
    public function sendKeyEvent(string $ip, int $keyCode, int $port = 5555): bool
    {
        try {
            if (!$this->connect($ip, $port)) {
                return false;
            }

            $command = "{$this->adbPath} -s {$ip}:{$port} shell input keyevent {$keyCode}";
            $result = Process::run($command);
            
            Log::info('ADB Key Event', [
                'ip' => $ip,
                'port' => $port,
                'keycode' => $keyCode,
                'command' => $command,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            return $result->successful();
        } catch (Exception $e) {
            Log::error('ADB Key Event Error', [
                'ip' => $ip,
                'keycode' => $keyCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Put device to sleep
     */
    public function sleep(string $ip, int $port = 5555): bool
    {
        // Key code 26 = Power button
        return $this->sendKeyEvent($ip, 26, $port);
    }

    /**
     * Wake up device
     */
    public function wakeUp(string $ip, int $port = 5555): bool
    {
        // Key code 26 = Power button (wake up)
        return $this->sendKeyEvent($ip, 26, $port);
    }

    /**
     * Check if device is connected
     */
    public function isConnected(string $ip, int $port = 5555): bool
    {
        try {
            $command = "{$this->adbPath} devices";
            $result = Process::run($command);
            
            $output = $result->output();
            $deviceAddress = "{$ip}:{$port}";
            
            return str_contains($output, $deviceAddress) && str_contains($output, 'device');
        } catch (Exception $e) {
            Log::error('ADB Check Connection Error', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get device info
     */
    public function getDeviceInfo(string $ip, int $port = 5555): array
    {
        try {
            if (!$this->connect($ip, $port)) {
                return [];
            }

            $commands = [
                'model' => "{$this->adbPath} -s {$ip}:{$port} shell getprop ro.product.model",
                'android_version' => "{$this->adbPath} -s {$ip}:{$port} shell getprop ro.build.version.release",
                'brand' => "{$this->adbPath} -s {$ip}:{$port} shell getprop ro.product.brand",
                'battery' => "{$this->adbPath} -s {$ip}:{$port} shell dumpsys battery | grep level",
            ];

            $info = [];
            foreach ($commands as $key => $command) {
                $result = Process::run($command);
                $info[$key] = trim($result->output());
            }

            return $info;
        } catch (Exception $e) {
            Log::error('ADB Get Device Info Error', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Execute custom ADB command
     */
    public function executeCommand(string $ip, string $command, int $port = 5555): array
    {
        try {
            if (!$this->connect($ip, $port)) {
                return ['success' => false, 'output' => '', 'error' => 'Connection failed'];
            }

            $fullCommand = "{$this->adbPath} -s {$ip}:{$port} {$command}";
            $result = Process::run($fullCommand);
            
            Log::info('ADB Custom Command', [
                'ip' => $ip,
                'port' => $port,
                'command' => $fullCommand,
                'output' => $result->output(),
                'success' => $result->successful()
            ]);
            
            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput()
            ];
        } catch (Exception $e) {
            Log::error('ADB Custom Command Error', [
                'ip' => $ip,
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'output' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current ADB path
     */
    public function getAdbExecutablePath(): string
    {
        return $this->adbPath;
    }
}