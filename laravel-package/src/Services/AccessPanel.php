<?php

namespace Ptcs\ZkTeco\Services;

use DateTime;
use Exception;
use Ptcs\ZkTeco\Models\AccessPanelDoorsStatus;
use Ptcs\ZkTeco\Models\AccessPanelEvent;
use Ptcs\ZkTeco\Models\AccessPanelRtEvent;
use Ptcs\ZkTeco\Models\Fingerprint;
use Ptcs\ZkTeco\Models\Transaction;
use Ptcs\ZkTeco\Models\User;
use Ptcs\ZkTeco\Models\UserAuthorization;
use Ptcs\ZkTeco\Readers\FpReader;
use Ptcs\ZkTeco\Readers\TransactionReader;
use Ptcs\ZkTeco\Readers\UserAuthReader;
use Ptcs\ZkTeco\Readers\UsersReader;

/**
 * ZKTeco Access Panel Communication Service
 *
 * This class provides a PHP implementation for communicating with ZKTeco
 * access panel devices via TCP socket connection. It replaces the C# DLL-based
 * implementation with pure PHP socket communication.
 */
class AccessPanel
{
    private const FP_TABLE_10 = 'templatev10';
    private const USER_TABLE = 'user';
    private const AUTH_TABLE = 'userauthorize';
    private const TIMEZONE_TABLE = 'timezone';
    private const TRANSACTIONS_TABLE = 'transaction';

    private const HUGE_BUFFER_SIZE = 20 * 1024 * 1024;
    private const LARGE_BUFFER_SIZE = 2 * 1024 * 1024;

    private const VERSION_2018 = 2018;
    private const VERSION_2014 = 2014;

    /** ZKTeco proprietary protocol packet header magic bytes */
    private const PACKET_HEADER = "\x50\x50\x82\x7D";

    /** Regex pattern for filtering printable ASCII characters (space to tilde) */
    private const PRINTABLE_ASCII_PATTERN = '/[^\x20-\x7E]/';

    /** @var resource|null Socket connection handle */
    private $socket = null;

    private string $ip = '';
    private int $port = 4370;
    private int $timeout = 5000;
    private int $sessionId = 0;
    private int $replyId = 0;

    private int $failCount = 0;
    private int $lastDataError = 0;
    private string $lastDataTable = '';

    public int $detectedFirmwareVersion = 0;

    /**
     * Get the last error code
     *
     * @deprecated Use getLastDataError() instead
     */
    public function getLastError(): int
    {
        return $this->getLastDataError();
    }

    /**
     * Get the last data operation error code
     */
    public function getLastDataError(): int
    {
        return $this->lastDataError;
    }

    /**
     * Get the last data table that caused an error
     */
    public function getLastDataErrorTable(): string
    {
        return $this->lastDataTable;
    }

    /**
     * Get detected firmware version
     */
    public function getDetectedFirmwareVersion(): int
    {
        return $this->detectedFirmwareVersion;
    }

    /**
     * Check if connected to device
     */
    public function isConnected(): bool
    {
        if ($this->socket !== null) {
            if ($this->failCount > 5) {
                $this->failCount = 0;
                $this->disconnect();
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Disconnect from device
     */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            $this->sendCommand('Disconnect', '');
            @fclose($this->socket);
            $this->socket = null;
            $this->sessionId = 0;
        }
    }

    /**
     * Connect to a ZKTeco device using TCP protocol
     */
    public function connect(string $ip, int $port = 4370, int $key = 0, int $timeout = 5000): bool
    {
        if ($this->isConnected()) {
            return false;
        }

        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;

        $timeoutSec = (int) ($timeout / 1000);
        $context = stream_context_create([
            'socket' => [
                'tcp_nodelay' => true,
            ]
        ]);

        $this->socket = @stream_socket_client(
            "tcp://{$ip}:{$port}",
            $errno,
            $errstr,
            $timeoutSec,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            $this->socket = null;
            return false;
        }

        stream_set_timeout($this->socket, $timeoutSec);
        stream_set_blocking($this->socket, true);

        // Send connection command
        $connStr = "protocol=TCP,ipaddress={$ip},port={$port},timeout={$timeout}";
        if ($key !== 0) {
            $connStr .= ",passwd={$key}";
        }

        if (!$this->sendCommand('Connect', $connStr)) {
            $this->disconnect();
            return false;
        }

        $this->detectVersion();
        return true;
    }

    /**
     * Send a command to the device
     */
    private function sendCommand(string $command, string $data): bool
    {
        if ($this->socket === null) {
            return false;
        }

        $packet = $this->createPacket($command, $data);
        $written = @fwrite($this->socket, $packet);

        if ($written === false || $written !== strlen($packet)) {
            return false;
        }

        return true;
    }

    /**
     * Create a command packet
     */
    private function createPacket(string $command, string $data): string
    {
        $commandBytes = $command . "\x00";
        $dataBytes = $data . "\x00";

        return self::PACKET_HEADER . $commandBytes . $dataBytes;
    }

    /**
     * Read response from device
     */
    private function readResponse(int $bufferSize = null): ?string
    {
        if ($this->socket === null) {
            return null;
        }

        $bufferSize = $bufferSize ?? self::LARGE_BUFFER_SIZE;
        $response = '';

        // Read until we get complete data or timeout
        while (true) {
            $chunk = @fread($this->socket, min(8192, $bufferSize - strlen($response)));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
            if (strlen($response) >= $bufferSize) {
                break;
            }
        }

        return $response !== '' ? $response : null;
    }

    /**
     * Detect firmware version
     */
    private function detectVersion(): void
    {
        $this->detectedFirmwareVersion = self::VERSION_2014;

        // Try to read from templatev10 table (2018 version)
        $result = $this->getDeviceData(self::FP_TABLE_10, 'FingerID', '');
        if ($result !== null) {
            $this->detectedFirmwareVersion = self::VERSION_2018;
        }
    }

    /**
     * Get device data from a table
     */
    private function getDeviceData(string $table, string $fields, string $filter): ?string
    {
        $this->lastDataTable = $table;

        $command = "GetDeviceData";
        $data = "table={$table},fieldnames={$fields}";
        if (!empty($filter)) {
            $data .= ",filter={$filter}";
        }

        if (!$this->sendCommand($command, $data)) {
            $this->lastDataError = -1;
            $this->failCount++;
            return null;
        }

        $response = $this->readResponse();
        if ($response === null) {
            $this->lastDataError = -1;
            $this->failCount++;
            return null;
        }

        $this->lastDataError = 0;
        return $response;
    }

    /**
     * Set device data to a table
     */
    private function setDeviceData(string $table, string $data): bool
    {
        $this->lastDataTable = $table;

        $command = "SetDeviceData";
        $packet = "table={$table},data={$data}";

        if (!$this->sendCommand($command, $packet)) {
            $this->lastDataError = -1;
            $this->failCount++;
            return false;
        }

        $response = $this->readResponse();
        if ($response === null) {
            $this->lastDataError = -1;
            $this->failCount++;
            return false;
        }

        $this->lastDataError = 0;
        return true;
    }

    /**
     * Delete device data from a table
     */
    private function deleteDeviceData(string $table, string $filter): bool
    {
        $this->lastDataTable = $table;

        $command = "DeleteDeviceData";
        $data = "table={$table}";
        if (!empty($filter)) {
            $data .= ",filter={$filter}";
        }

        if (!$this->sendCommand($command, $data)) {
            $this->lastDataError = -1;
            $this->failCount++;
            return false;
        }

        $response = $this->readResponse();
        $this->lastDataError = 0;
        return true;
    }

    /**
     * Control device (open door, stop alarm, etc.)
     */
    private function controlDevice(int $operation, int $p1, int $p2, int $p3, int $p4): bool
    {
        $command = "ControlDevice";
        $data = "operation={$operation},p1={$p1},p2={$p2},p3={$p3},p4={$p4}";

        if (!$this->sendCommand($command, $data)) {
            $this->failCount++;
            return false;
        }

        $response = $this->readResponse();
        return $response !== null;
    }

    /**
     * Get device parameter
     */
    private function getDeviceParam(string $item): ?string
    {
        $command = "GetDeviceParam";
        $data = "item={$item}";

        if (!$this->sendCommand($command, $data)) {
            $this->failCount++;
            return null;
        }

        $response = $this->readResponse();
        if ($response === null) {
            $this->failCount++;
            return null;
        }

        // Parse response
        $pattern = "/{$item}=([^\r\n]+)/";
        if (preg_match($pattern, $response, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Set device parameter
     */
    private function setDeviceParam(string $item): bool
    {
        $command = "SetDeviceParam";

        if (!$this->sendCommand($command, $item)) {
            $this->failCount++;
            return false;
        }

        $response = $this->readResponse();
        return $response !== null;
    }

    /**
     * Read a fingerprint from the device
     */
    public function getFingerprint(string $pin, int $finger): ?Fingerprint
    {
        if (!$this->isConnected()) {
            return null;
        }

        $fields = "Size\tPin\tFingerID\tValid\tTemplate\tEndTag";
        $filter = "Pin={$pin},FingerID={$finger},Valid=1";

        $data = $this->getDeviceData(self::FP_TABLE_10, $fields, $filter);
        if ($data === null) {
            return null;
        }

        $reader = new FpReader($data);
        if (!$reader->readHead()) {
            return null;
        }

        for ($i = 0; $i < $reader->lineCount; $i++) {
            $fp = $reader->next();
            if ($fp !== null && $fp->fingerId === $finger) {
                return $fp;
            }
        }

        return new Fingerprint($pin, $finger, null, null);
    }

    /**
     * Read doors that a user is allowed to access
     */
    public function readDoors(string $pin, int $timezone = 1): int
    {
        if (!$this->isConnected()) {
            return -1;
        }

        $fields = "Pin\tAuthorizeTimezoneId\tAuthorizeDoorId";
        $filter = "AuthorizeTimezoneId={$timezone},Pin={$pin}";

        $data = $this->getDeviceData(self::AUTH_TABLE, $fields, $filter);
        if ($data === null) {
            return -1;
        }

        $reader = new UserAuthReader($data);
        if (!$reader->readHead()) {
            return -1;
        }

        $auth = $reader->next();
        if ($auth === null) {
            throw new Exception('Could not parse auth data');
        }

        return $auth->doors;
    }

    /**
     * Read doors for a list of users
     */
    private function readDoorsForUsers(array &$users, int $timezone = 1): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        $fields = "Pin\tAuthorizeTimezoneId\tAuthorizeDoorId";
        $filter = "AuthorizeTimezoneId={$timezone}";

        $data = $this->getDeviceData(self::AUTH_TABLE, $fields, $filter);
        if ($data === null) {
            return false;
        }

        $reader = new UserAuthReader($data);
        if (!$reader->readHead()) {
            return false;
        }

        // Create a map for quick lookup
        $userMap = [];
        foreach ($users as $index => $user) {
            $userMap[$user->pin] = $index;
        }

        for ($i = 0; $i < $reader->lineCount; $i++) {
            $auth = $reader->next();
            if ($auth === null) {
                throw new Exception('Could not parse auth data');
            }

            if (isset($userMap[$auth->pin])) {
                $users[$userMap[$auth->pin]]->setDoorsByFlag($auth->doors);
            }
        }

        return true;
    }

    /**
     * Read fingerprints for a list of users
     */
    private function readFingerprintsForUsers(array &$users): bool
    {
        if ($this->detectedFirmwareVersion === self::VERSION_2014) {
            return true; // Not supported, skip
        }

        if (!$this->isConnected()) {
            return false;
        }

        $fields = "Size\tPin\tFingerID\tValid\tEndTag";
        $filter = "Valid=1";

        $data = $this->getDeviceData(self::FP_TABLE_10, $fields, $filter);
        if ($data === null) {
            return false;
        }

        $reader = new FpReader($data);
        if (!$reader->readHead()) {
            return false;
        }

        // Create a map for quick lookup
        $userMap = [];
        foreach ($users as $index => $user) {
            $userMap[$user->pin] = $index;
        }

        for ($i = 0; $i < $reader->lineCount; $i++) {
            $fp = $reader->next();
            if ($fp === null) {
                throw new Exception('Could not parse fingerprints');
            }

            if (isset($userMap[$fp->pin])) {
                $users[$userMap[$fp->pin]]->addFingerprint($fp);
            }
        }

        return true;
    }

    /**
     * Read the list of users from the device
     *
     * @return User[]|null
     */
    public function readUsers(): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }

        $data = $this->getDeviceData(self::USER_TABLE, '*', '');
        if ($data === null) {
            return null;
        }

        $reader = new UsersReader($data);
        if (!$reader->readHead()) {
            return null;
        }

        if ($reader->lineCount < 1) {
            return [];
        }

        $users = [];
        for ($i = 0; $i < $reader->lineCount; $i++) {
            $user = $reader->next();
            if ($user === null) {
                throw new Exception('Could not parse users');
            }
            $users[] = $user;
        }

        // Sort users by PIN
        usort($users, fn(User $a, User $b) => $a->compareTo($b));

        if (!$this->readFingerprintsForUsers($users)) {
            return null;
        }

        if (!$this->readDoorsForUsers($users, 1)) {
            return null;
        }

        return $users;
    }

    /**
     * Read device transaction log
     *
     * @return Transaction[]|null
     */
    public function readTransactionLog(DateTime $start): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }

        $data = $this->getDeviceData(self::TRANSACTIONS_TABLE, '*', '');
        if ($data === null) {
            return null;
        }

        $reader = new TransactionReader($data);
        if (!$reader->readHead()) {
            return null;
        }

        $transactions = [];
        for ($i = 0; $i < $reader->lineCount; $i++) {
            $t = $reader->next();
            if ($t === null) {
                continue;
            }

            if ($t->timestamp >= $start) {
                $transactions[] = $t;
            }
        }

        usort($transactions, fn(Transaction $a, Transaction $b) => $a->compareTo($b));

        return $transactions;
    }

    /**
     * Validate password string
     */
    public static function isPasswordValid(string $password): bool
    {
        if ($password === '') {
            return true;
        }

        return ctype_digit($password);
    }

    /**
     * Validate card number
     */
    public static function isCardValid(?string $card): bool
    {
        if ($card === null) {
            return false;
        }
        if ($card === '') {
            return true;
        }

        return ctype_digit($card);
    }

    /**
     * Validate PIN
     */
    public static function isPinValid(string $pin): bool
    {
        if (empty(trim($pin))) {
            return false;
        }

        if (!ctype_digit($pin)) {
            return false;
        }

        return bccomp($pin, (string) PHP_INT_MAX) <= 0;
    }

    /**
     * Write a single user to the device
     */
    public function writeUser(User $user): bool
    {
        return $this->writeUsers([$user]);
    }

    /**
     * Delete a user from the device
     */
    public function deleteUser(string $pin): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if (!$this->deleteUserFingerprints($pin)) {
            return false;
        }

        // May fail sometimes, but we continue
        $this->deleteUserTimezones($pin);

        if (!$this->deleteDeviceData(self::USER_TABLE, "Pin={$pin}")) {
            return false;
        }

        return true;
    }

    /**
     * Set user door access
     */
    public function setUserDoors(string $pin, int $timezone, array $doors): bool
    {
        if (!$this->deleteDeviceData(self::AUTH_TABLE, "Pin={$pin}")) {
            return false;
        }

        $data = $this->authTableData($pin, $timezone, $doors);
        return $this->setDeviceData(self::AUTH_TABLE, $data);
    }

    /**
     * Delete user by card number
     */
    public function deleteUserByCard(string $card): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::USER_TABLE, "CardNo={$card}");
    }

    /**
     * Delete user fingerprints
     */
    public function deleteUserFingerprints(string $pin): bool
    {
        if ($this->detectedFirmwareVersion === self::VERSION_2014) {
            return true; // Not supported, skip
        }

        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::FP_TABLE_10, "Pin={$pin}");
    }

    /**
     * Delete user timezones (door access)
     */
    public function deleteUserTimezones(string $pin): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::AUTH_TABLE, "Pin={$pin}");
    }

    /**
     * Delete all data from device
     */
    public function deleteAllDataFromDevice(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if ($this->detectedFirmwareVersion === self::VERSION_2018) {
            if (!$this->deleteDeviceData(self::FP_TABLE_10, '')) {
                return false;
            }
        }

        return $this->deleteDeviceData(self::USER_TABLE, '')
            && $this->deleteDeviceData(self::AUTH_TABLE, '')
            && $this->deleteDeviceData(self::TIMEZONE_TABLE, '')
            && $this->deleteDeviceData(self::TRANSACTIONS_TABLE, '');
    }

    /**
     * Delete all fingerprints
     */
    public function deleteAllFingerprints(): bool
    {
        if ($this->detectedFirmwareVersion === self::VERSION_2014) {
            return true; // Not supported, skip
        }

        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::FP_TABLE_10, '');
    }

    /**
     * Delete all users
     */
    public function deleteAllUsers(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::USER_TABLE, '');
    }

    /**
     * Delete all user authorizations
     */
    public function deleteAllUserAuth(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::AUTH_TABLE, '');
    }

    /**
     * Delete all timezones
     */
    public function deleteAllTimezones(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::TIMEZONE_TABLE, '');
    }

    /**
     * Delete all transactions
     */
    public function deleteAllTransactions(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::TRANSACTIONS_TABLE, '');
    }

    /**
     * Delete one fingerprint
     */
    public function deleteFingerprint(string $pin, int $finger): bool
    {
        if ($this->detectedFirmwareVersion === self::VERSION_2014) {
            return true; // Not supported, skip
        }

        if (!$this->isConnected()) {
            return false;
        }

        return $this->deleteDeviceData(self::FP_TABLE_10, "Pin={$pin},FingerID={$finger}");
    }

    /**
     * Build timezone string
     */
    private function timezoneString(int $id, array $conf): string
    {
        if (count($conf) !== 21) {
            throw new Exception('Invalid timezone length, A Timezone must be int[3*7]');
        }

        return sprintf(
            "TimezoneId=%d" .
            "\tSunTime1=%d\tSunTime2=%d\tSunTime3=%d" .
            "\tMonTime1=%d\tMonTime2=%d\tMonTime3=%d" .
            "\tTueTime1=%d\tTueTime2=%d\tTueTime3=%d" .
            "\tWedTime1=%d\tWedTime2=%d\tWedTime3=%d" .
            "\tThuTime1=%d\tThuTime2=%d\tThuTime3=%d" .
            "\tFriTime1=%d\tFriTime2=%d\tFriTime3=%d" .
            "\tSatTime1=%d\tSatTime2=%d\tSatTime3=%d" .
            "\tHol1Time1=2359\tHol1Time2=0\tHol1Time3=0" .
            "\tHol2Time1=2359\tHol2Time2=0\tHol2Time3=0" .
            "\tHol3Time1=2359\tHol3Time2=0\tHol3Time3=0",
            $id,
            $conf[6], $conf[7], $conf[8],   // Sun
            $conf[9], $conf[10], $conf[11], // Mon
            $conf[12], $conf[13], $conf[14], // Tue
            $conf[15], $conf[16], $conf[17], // Wed
            $conf[18], $conf[19], $conf[20], // Thu
            $conf[0], $conf[1], $conf[2],   // Fri
            $conf[3], $conf[4], $conf[5]    // Sat
        );
    }

    /**
     * Write a timezone to the device
     *
     * A timezone is an int[21], every 3 ints are 3 periods in the day.
     * A period is a 32bit int, where the HIGH 16 bits are the start of the period,
     * and the low 16 bits are the end.
     * 16bits time format is hours*100+minutes.
     * To select the entire day set the first period to 2359 and the 2nd & 3rd to zero.
     *
     * @param int $id Timezone ID
     * @param int[] $tz Array of 21 integers defining the timezone
     */
    public function writeTimezone(int $id, array $tz): bool
    {
        $data = $this->timezoneString($id, $tz);
        return $this->setDeviceData(self::TIMEZONE_TABLE, $data);
    }

    /**
     * Build auth table data string
     */
    private function authTableData(string $pin, int $timezoneId, ?array $doors): string
    {
        $doorsCode = 0;
        if ($doors !== null) {
            foreach ($doors as $door) {
                $doorsCode |= (1 << ($door - 1));
            }
        }

        return "Pin={$pin}\tAuthorizeTimezoneId={$timezoneId}\tAuthorizeDoorId={$doorsCode}";
    }

    /**
     * Write users to the device
     *
     * @param User[] $users
     */
    public function writeUsers(array $users): bool
    {
        if (!$this->isConnected() || empty($users)) {
            return false;
        }

        // Write users in batches of 100
        for ($k = 0; $k < count($users); $k += 100) {
            $batch = [];
            $end = min($k + 100, count($users));

            for ($i = $k; $i < $end; $i++) {
                $format = $this->detectedFirmwareVersion === self::VERSION_2018
                    ? $users[$i]->to2018Format()
                    : $users[$i]->to2014Format();
                $batch[] = $format;
            }

            $data = implode("\r\n", $batch);
            if (!$this->setDeviceData(self::USER_TABLE, $data)) {
                return false;
            }
        }

        // Write user authorizations
        for ($k = 0; $k < count($users); $k += 100) {
            $batch = [];
            $end = min($k + 100, count($users));

            for ($i = $k; $i < $end; $i++) {
                $batch[] = $this->authTableData($users[$i]->pin, 1, $users[$i]->doors);
            }

            $data = implode("\r\n", $batch);
            if (!$this->setDeviceData(self::AUTH_TABLE, $data)) {
                return false;
            }
        }

        // Write fingerprints (2018 version only)
        if ($this->detectedFirmwareVersion === self::VERSION_2018) {
            $fingerprints = [];
            foreach ($users as $user) {
                foreach ($user->fingerprints as $fp) {
                    if ($fp->hasValidTemplate()) {
                        $fingerprints[] = $fp;
                    }
                }
            }

            for ($k = 0; $k < count($fingerprints); $k += 20) {
                $batch = [];
                $end = min($k + 20, count($fingerprints));

                for ($i = $k; $i < $end; $i++) {
                    $batch[] = (string) $fingerprints[$i];
                }

                $data = implode("\r\n", $batch);
                if (!$this->setDeviceData(self::FP_TABLE_10, $data)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Write a single fingerprint
     */
    public function writeFingerprint(Fingerprint $fp): bool
    {
        return $this->writeFingerprints([$fp]);
    }

    /**
     * Write multiple fingerprints
     *
     * @param Fingerprint[] $fpList
     */
    public function writeFingerprints(array $fpList): bool
    {
        if ($this->detectedFirmwareVersion === self::VERSION_2014) {
            return true; // Not supported, skip
        }

        if (!$this->isConnected() || empty($fpList)) {
            return false;
        }

        $batch = [];
        foreach ($fpList as $fp) {
            if (!self::isPinValid($fp->pin) || !$fp->hasValidTemplate()) {
                return false;
            }
            $batch[] = (string) $fp;
        }

        $data = implode("\r\n", $batch);
        return $this->setDeviceData(self::FP_TABLE_10, $data);
    }

    /**
     * Stop alarm
     */
    public function stopAlarm(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->controlDevice(2, 0, 0, 0, 0);
    }

    /**
     * Open a door for specified seconds
     *
     * @param int $doorId Door ID (first door is 1)
     * @param int $seconds Duration in seconds (1-60, or 255 for permanent)
     */
    public function openDoor(int $doorId, int $seconds = 5): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        // 255 seconds = open without time limit
        if ($seconds < 1 || ($seconds > 60 && $seconds !== 255)) {
            return false;
        }

        return $this->controlDevice(1, $doorId, 1, $seconds, 0);
    }

    /**
     * Close a door
     *
     * @param int $doorId Door ID (first door is 1)
     */
    public function closeDoor(int $doorId): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->controlDevice(1, $doorId, 1, 0, 0);
    }

    /**
     * Get door count
     */
    public function getDoorCount(): int
    {
        if (!$this->isConnected()) {
            return -1;
        }

        $result = $this->getDeviceParam('LockCount');
        if ($result === null) {
            return -1;
        }

        return (int) $result;
    }

    /**
     * Get device serial number
     */
    public function getSerialNumber(): ?string
    {
        if (!$this->isConnected()) {
            return null;
        }

        $result = $this->getDeviceParam('~SerialNumber');
        if ($result === null) {
            return null;
        }

        // Filter to printable ASCII characters
        return preg_replace(self::PRINTABLE_ASCII_PATTERN, '', $result);
    }

    /**
     * Encode a DateTime to ZKTeco timestamp format
     *
     * ZKTeco uses a custom timestamp encoding:
     * - Years are offset from 2000
     * - Each month is assumed to have 31 days
     * - The formula: ((year-2000)*12*31 + (month-1)*31 + (day-1)) * 86400 + hour*3600 + minute*60 + second
     *
     * @param DateTime $time The datetime to encode
     * @return int The ZKTeco encoded timestamp
     */
    private function encodeZkTimestamp(DateTime $time): int
    {
        $year = (int) $time->format('Y');
        $month = (int) $time->format('m');
        $day = (int) $time->format('d');
        $hour = (int) $time->format('H');
        $minute = (int) $time->format('i');
        $second = (int) $time->format('s');

        $daysPart = ($year - 2000) * 12 * 31 + ($month - 1) * 31 + ($day - 1);
        $secondsInDay = 24 * 60 * 60;

        return $daysPart * $secondsInDay + $hour * 3600 + $minute * 60 + $second;
    }

    /**
     * Set device time
     */
    public function setDeviceTime(DateTime $time): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        $val = $this->encodeZkTimestamp($time);

        return $this->setDeviceParam("DateTime={$val}");
    }

    /**
     * Reboot the device
     */
    public function reboot(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->setDeviceParam('Reboot=1');
    }

    /**
     * Get event log (latest events only)
     */
    public function getEventLog(): ?AccessPanelEvent
    {
        if (!$this->isConnected()) {
            return null;
        }

        $command = "GetRTLog";
        if (!$this->sendCommand($command, '')) {
            $this->failCount++;
            return null;
        }

        $response = $this->readResponse();
        if ($response === null) {
            $this->failCount++;
            return null;
        }

        // Parse response
        $lines = preg_split('/\r\n/', trim($response), -1, PREG_SPLIT_NO_EMPTY);
        $events = [];
        $doorsStatus = null;

        foreach ($lines as $line) {
            if (empty($line) || $line[0] === "\0") {
                continue;
            }

            $values = explode(',', $line);
            if (count($values) !== 7) {
                continue;
            }

            if ($values[4] === '255') {
                $doorsStatus = new AccessPanelDoorsStatus($values[1], $values[2]);
            } else {
                $card = is_numeric($values[2]) ? (int) $values[2] : 0;
                $events[] = new AccessPanelRtEvent(
                    $values[0],
                    $values[1],
                    $values[3],
                    (int) $values[4],
                    (int) $values[5],
                    $card
                );
            }
        }

        return new AccessPanelEvent($doorsStatus, $events);
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
