<?php

namespace Ptcs\ZkTeco\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool connect(string $ip, int $port, int $key = 0, int $timeout = 5000)
 * @method static void disconnect()
 * @method static bool isConnected()
 * @method static array|null readUsers()
 * @method static bool writeUser(\Ptcs\ZkTeco\Models\User $user)
 * @method static bool writeUsers(array $users)
 * @method static bool deleteUser(string $pin)
 * @method static bool openDoor(int $doorId, int $seconds = 5)
 * @method static bool closeDoor(int $doorId)
 * @method static int getDoorCount()
 * @method static string|null getSerialNumber()
 * @method static array|null readTransactionLog(\DateTime $start)
 * @method static \Ptcs\ZkTeco\Models\AccessPanelEvent|null getEventLog()
 * @method static bool writeTimezone(int $id, array $tz)
 * @method static int getLastError()
 * @method static int getDetectedFirmwareVersion()
 *
 * @see \Ptcs\ZkTeco\Services\AccessPanel
 */
class ZkTeco extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'zkteco';
    }
}
