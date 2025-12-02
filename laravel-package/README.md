# ZKTeco PullSDK Laravel Wrapper

A Laravel PHP package for communicating with ZKTeco access panel devices that support PULL-SDK protocol.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x

## Installation

Install the package via Composer:

```bash
composer require ptcs/zkteco-pullsdk
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=zkteco-config
```

## Configuration

After publishing, you can configure the package in `config/zkteco.php`:

```php
return [
    'default' => env('ZKTECO_DEFAULT_DEVICE', 'default'),

    'devices' => [
        'default' => [
            'ip' => env('ZKTECO_IP', '192.168.1.201'),
            'port' => env('ZKTECO_PORT', 4370),
            'password' => env('ZKTECO_PASSWORD', 0),
            'timeout' => env('ZKTECO_TIMEOUT', 5000),
        ],
    ],

    'default_timezone_id' => env('ZKTECO_DEFAULT_TIMEZONE_ID', 1),
];
```

Add the following to your `.env` file:

```env
ZKTECO_IP=192.168.1.201
ZKTECO_PORT=4370
ZKTECO_PASSWORD=0
ZKTECO_TIMEOUT=5000
```

## Usage

### Using the Facade

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

// Connect to device
if (!ZkTeco::connect('192.168.1.201', 4370, 123456, 5000)) {
    return; // Could not connect
}

// Read users
$users = ZkTeco::readUsers();
if ($users === null) {
    return; // Could not read users
}

// Open door 1 for 5 seconds
if (!ZkTeco::openDoor(1, 5)) {
    return; // Could not open door
}

// Disconnect
ZkTeco::disconnect();
```

### Using Dependency Injection

```php
use Ptcs\ZkTeco\Services\AccessPanel;

class DeviceController extends Controller
{
    public function index(AccessPanel $device)
    {
        if (!$device->connect('192.168.1.201', 4370)) {
            return response()->json(['error' => 'Connection failed'], 500);
        }

        $users = $device->readUsers();
        $device->disconnect();

        return response()->json($users);
    }
}
```

### Managing Users

```php
use Ptcs\ZkTeco\Facades\ZkTeco;
use Ptcs\ZkTeco\Models\User;
use Ptcs\ZkTeco\Models\Fingerprint;

// Connect
ZkTeco::connect('192.168.1.201', 4370, 123456, 5000);

// Read all users
$users = ZkTeco::readUsers();

// Add a new user
$user = new User(
    pin: '911',
    name: '911 Carrera 4',
    card: '27012235',
    password: '9112001',
    startTime: '20010911',
    endTime: '20231007'
);

// Give the user access to specific doors (door 1, 2, and 4)
$user->setDoorsByFlag(1 | 2 | 8); // Binary flags: door 1=1, door 2=2, door 4=8

// Add fingerprints to user
$user->addFingerprint(new Fingerprint(
    pin: $user->pin,
    fingerId: 5,
    template: 'base64_encoded_template_here',
    endTag: '13'
));

// Write user to device
if (!ZkTeco::writeUser($user)) {
    return; // Could not write user
}

// Delete a user
ZkTeco::deleteUser('911');

// Disconnect
ZkTeco::disconnect();
```

### Working with Fingerprints

```php
use Ptcs\ZkTeco\Facades\ZkTeco;
use Ptcs\ZkTeco\Models\Fingerprint;

ZkTeco::connect('192.168.1.201', 4370);

// Read a fingerprint
$fingerprint = ZkTeco::getFingerprint('911', 5);

// Add a fingerprint to existing user
$fp = new Fingerprint(
    pin: '911',
    fingerId: 2,
    template: 'base64_encoded_template_here',
    endTag: '13'
);

ZkTeco::writeFingerprint($fp);

// Delete a fingerprint
ZkTeco::deleteFingerprint('911', 2);

ZkTeco::disconnect();
```

### Setting Timezones (Working Hours)

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

ZkTeco::connect('192.168.1.201', 4370);

// Set 24/7 access timezone
// 21 values: 3 periods × 7 days (Friday first)
// Each period: high 16 bits = start time, low 16 bits = end time
// Time format: hours * 100 + minutes (e.g., 2359 = 23:59)
$defaultTimezone = [
    2359, 0, 0, // Friday
    2359, 0, 0, // Saturday
    2359, 0, 0, // Sunday
    2359, 0, 0, // Monday
    2359, 0, 0, // Tuesday
    2359, 0, 0, // Wednesday
    2359, 0, 0, // Thursday
];

ZkTeco::writeTimezone(1, $defaultTimezone);

ZkTeco::disconnect();
```

### Reading Transaction Logs

```php
use Ptcs\ZkTeco\Facades\ZkTeco;
use DateTime;

ZkTeco::connect('192.168.1.201', 4370);

// Read transactions from a specific date
$startDate = new DateTime('2023-01-01 00:00:00');
$transactions = ZkTeco::readTransactionLog($startDate);

foreach ($transactions as $transaction) {
    echo sprintf(
        "User: %s, Door: %d, Time: %s, %s\n",
        $transaction->pin,
        $transaction->door,
        $transaction->timestamp->format('Y-m-d H:i:s'),
        $transaction->isAccessGranted() ? 'Granted' : 'Denied'
    );
}

ZkTeco::disconnect();
```

### Real-time Event Monitoring

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

ZkTeco::connect('192.168.1.201', 4370);

// Get latest events
$eventLog = ZkTeco::getEventLog();

if ($eventLog !== null) {
    // Check door status
    if ($eventLog->hasDoorsStatus()) {
        for ($i = 0; $i < 4; $i++) {
            echo "Door {$i}: " . $eventLog->doorsStatus->getStatusString($i) . "\n";
        }
    }

    // Process events
    foreach ($eventLog->events as $event) {
        echo $event . "\n";
    }
}

ZkTeco::disconnect();
```

### Device Information

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

ZkTeco::connect('192.168.1.201', 4370);

// Get serial number
$serialNumber = ZkTeco::getSerialNumber();

// Get door count
$doorCount = ZkTeco::getDoorCount();

// Get firmware version
$firmwareVersion = ZkTeco::getDetectedFirmwareVersion();

// Set device time
ZkTeco::setDeviceTime(new DateTime());

// Reboot device
ZkTeco::reboot();

ZkTeco::disconnect();
```

### Door Control

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

ZkTeco::connect('192.168.1.201', 4370);

// Open door 1 for 5 seconds
ZkTeco::openDoor(1, 5);

// Open door permanently (255 seconds = no time limit)
ZkTeco::openDoor(1, 255);

// Close door
ZkTeco::closeDoor(1);

// Stop alarm
ZkTeco::stopAlarm();

ZkTeco::disconnect();
```

### Bulk Operations

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

ZkTeco::connect('192.168.1.201', 4370);

// Delete all data (use with caution!)
ZkTeco::deleteAllDataFromDevice();

// Delete specific data types
ZkTeco::deleteAllUsers();
ZkTeco::deleteAllFingerprints();
ZkTeco::deleteAllTimezones();
ZkTeco::deleteAllTransactions();
ZkTeco::deleteAllUserAuth();

ZkTeco::disconnect();
```

## Models

### User

| Property | Type | Description |
|----------|------|-------------|
| pin | string | User ID/PIN |
| name | string | User name |
| card | string | Card number |
| password | string | Password |
| startTime | string | Access start date (YYYYMMDD) |
| endTime | string | Access end date (YYYYMMDD) |
| doors | array | Array of door IDs user can access |
| fingerprints | array | Array of Fingerprint objects |

### Fingerprint

| Property | Type | Description |
|----------|------|-------------|
| pin | string | User ID/PIN |
| fingerId | int | Finger index (0-9) |
| template | string | Base64 encoded fingerprint template |
| endTag | string | End tag value |

### Transaction

| Property | Type | Description |
|----------|------|-------------|
| verificationMethod | int | How user was verified (1=Finger, 3=Password, 4=Card) |
| card | string | Card number |
| pin | string | User ID/PIN |
| door | int | Door ID |
| event | int | Event type code |
| inOutState | int | 0=Entry, 1=Exit |
| timestamp | DateTime | Event timestamp |

## Error Handling

```php
use Ptcs\ZkTeco\Facades\ZkTeco;

try {
    if (!ZkTeco::connect('192.168.1.201', 4370)) {
        $error = ZkTeco::getLastError();
        throw new \Exception("Connection failed with error code: {$error}");
    }

    $users = ZkTeco::readUsers();
    if ($users === null) {
        $error = ZkTeco::getLastDataError();
        $table = ZkTeco::getLastDataErrorTable();
        throw new \Exception("Failed to read from {$table}, error: {$error}");
    }

    // ... process users

} catch (\Exception $e) {
    // Handle error
    Log::error('ZKTeco error: ' . $e->getMessage());
} finally {
    ZkTeco::disconnect();
}
```

## Firmware Versions

This package supports two firmware versions:

- **2014**: Basic user management, no fingerprint support
- **2018**: Full support including fingerprints and extended user fields

The firmware version is automatically detected when connecting to the device.

## Notes

- This package uses TCP socket communication to connect directly to ZKTeco devices
- Make sure the device is accessible from your Laravel server
- Some operations may take time depending on the amount of data
- Always disconnect when finished to free up device connections

## License

MIT License
