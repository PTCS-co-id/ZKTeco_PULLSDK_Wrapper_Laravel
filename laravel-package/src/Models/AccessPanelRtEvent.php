<?php

namespace Ptcs\ZkTeco\Models;

class AccessPanelRtEvent
{
    public string $time;
    public string $pin;
    public string $door;
    public int $card;
    public int $eventType;
    public int $inOrOut;

    private const EVENT_DESCRIPTIONS = [
        0 => 'Normal Punch Open',
        1 => 'Punch during Normal Open Time Zone',
        2 => 'First Card Normal Open',
        3 => 'Multi-Card Open',
        4 => 'Emergency Password Open',
        5 => 'Open during Normal Open Time Zone',
        6 => 'Linkage Event Triggered',
        7 => 'Alarm Canceled',
        8 => 'Remote Opening',
        9 => 'Remote Closing',
        10 => 'Disable Intraday Normal Open Time Zone',
        11 => 'Enable Intraday Normal Open Time Zone',
        12 => 'Open Auxiliary Output',
        13 => 'Close Auxiliary Output',
        14 => 'Press Fingerprint Open',
        15 => 'Multi-Card Open',
        16 => 'Press Fingerprint during Normal Open Time Zone',
        17 => 'Card plus Fingerprint Open',
        18 => 'First Card Normal Open',
        19 => 'First Card Normal Open',
        20 => 'Too Short Punch Interval',
        21 => 'Door Inactive Time Zone',
        22 => 'Illegal Time Zone',
        23 => 'Access Denied',
        24 => 'Anti-Passback',
        25 => 'Interlock',
        26 => 'Multi-Card Authentication',
        27 => 'Unregistered Card',
        28 => 'Opening Timeout',
        29 => 'Card Expired',
        30 => 'Password Error',
        31 => 'Too Short Fingerprint Pressing Interval',
        32 => 'Multi-Card Authentication',
        33 => 'Fingerprint Expired',
        34 => 'Unregistered Fingerprint',
        35 => 'Door Inactive Time Zone',
        36 => 'Door Inactive Time Zone',
        37 => 'Failed to Close during Normal Open Time Zone',
        101 => 'Duress Password Open',
        102 => 'Opened Accidentally',
        103 => 'Duress Fingerprint Open',
        200 => 'Door Opened Correctly',
        204 => 'Normal Open Time Zone Over',
        205 => 'Remote Normal Opening',
        206 => 'Device Start',
        220 => 'Auxiliary Input Disconnected',
        221 => 'Auxiliary Input Shorted',
    ];

    public function __construct(
        string $time,
        string $pin,
        string $door,
        int $eventType,
        int $inOrOut,
        int $card = 0
    ) {
        $this->time = $time;
        $this->pin = $pin;
        $this->door = $door;
        $this->eventType = $eventType;
        $this->inOrOut = $inOrOut;
        $this->card = $card;
    }

    /**
     * Get door ID as integer
     */
    public function getDoorId(): int
    {
        if (empty($this->door)) {
            return -1;
        }
        return is_numeric($this->door) ? (int) $this->door : -1;
    }

    /**
     * Get event description
     */
    public static function getDescription(int $code): ?string
    {
        return self::EVENT_DESCRIPTIONS[$code] ?? null;
    }

    public function __toString(): string
    {
        $description = self::getDescription($this->eventType) ?? 'Unknown event';

        if (!empty($this->pin) && $this->pin !== '0') {
            $type = match ($this->inOrOut) {
                0 => 'Entry',
                1 => 'Exit',
                default => 'User',
            };
            $description .= ", {$type}: {$this->pin}";
        }

        if (!empty($this->door) && $this->door !== '0') {
            $description .= ", Door: {$this->door}";
        }

        return "* Event: {$description}";
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'pin' => $this->pin,
            'door' => $this->door,
            'doorId' => $this->getDoorId(),
            'card' => $this->card,
            'eventType' => $this->eventType,
            'eventDescription' => self::getDescription($this->eventType),
            'inOrOut' => $this->inOrOut,
            'isEntry' => $this->inOrOut === 0,
            'isExit' => $this->inOrOut === 1,
        ];
    }
}
