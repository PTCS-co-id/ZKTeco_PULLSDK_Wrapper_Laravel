<?php

namespace Ptcs\ZkTeco\Models;

use DateTime;

class Transaction
{
    public int $verificationMethod;
    public string $card;
    public string $pin;
    public int $door;
    public int $event;
    public int $inOutState;
    public DateTime $timestamp;

    public const DEVICE_BOOT_EVENT = 206;

    private const ACCESS_GRANTED_CODES = [
        0,  // Normal Punch Open
        1,  // Punch during Normal Open Time Zone
        2,  // First Card Normal Open
        3,  // Multi-Card Open
        4,  // Emergency Password Open
        5,  // Open during Normal Open Time Zone
        14, // Press Fingerprint Open
        15, // Multi-Card Open
        16, // Press Fingerprint during Normal Open Time Zone
        17, // Card plus Fingerprint Open
        18, // First Card Normal Open
        19, // First Card Normal Open
        26, // Multi-Card Authentication
        32, // Multi-Card Authentication
    ];

    private const ACCESS_DENIED_CODES = [
        23, // Access Denied
        27, // Unregistered Card
        29, // Card Expired
        30, // Password Error
        33, // Fingerprint Expired
        34, // Unregistered Fingerprint
    ];

    public function __construct(
        int $verificationMethod,
        string $card,
        string $pin,
        int $door,
        int $event,
        int $inOutState,
        DateTime $timestamp
    ) {
        $this->verificationMethod = $verificationMethod;
        $this->card = $card;
        $this->pin = $pin;
        $this->door = $door;
        $this->event = $event;
        $this->inOutState = $inOutState;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the name of the verification method
     */
    public function getVerificationMethodName(): string
    {
        return match ($this->verificationMethod) {
            1 => 'Finger',
            3 => 'Password',
            4 => 'Card',
            11 => 'Card+Password',
            200 => 'Other',
            default => 'Unknown',
        };
    }

    /**
     * Check if access was granted
     */
    public function isAccessGranted(): bool
    {
        return in_array($this->event, self::ACCESS_GRANTED_CODES, true);
    }

    /**
     * Check if access was denied
     */
    public function isAccessDenied(): bool
    {
        return in_array($this->event, self::ACCESS_DENIED_CODES, true);
    }

    /**
     * Check if this is an entry event
     */
    public function isEntry(): bool
    {
        return $this->inOutState === 0;
    }

    /**
     * Check if this is an exit event
     */
    public function isExit(): bool
    {
        return $this->inOutState === 1;
    }

    /**
     * Compare transactions (for sorting by timestamp)
     */
    public function compareTo(?Transaction $other): int
    {
        if ($other === null) {
            return 1;
        }
        if ($this->timestamp < $other->timestamp) {
            return -1;
        }
        if ($this->timestamp > $other->timestamp) {
            return 1;
        }
        return strcmp($this->pin, $other->pin);
    }

    /**
     * Create transaction from array
     */
    public static function fromArray(array $data): self
    {
        $timestamp = $data['timestamp'] instanceof DateTime
            ? $data['timestamp']
            : new DateTime($data['timestamp'] ?? 'now');

        return new self(
            $data['verificationMethod'] ?? 0,
            $data['card'] ?? '',
            $data['pin'] ?? '',
            $data['door'] ?? 0,
            $data['event'] ?? 0,
            $data['inOutState'] ?? 0,
            $timestamp
        );
    }

    /**
     * Convert transaction to array
     */
    public function toArray(): array
    {
        return [
            'verificationMethod' => $this->verificationMethod,
            'verificationMethodName' => $this->getVerificationMethodName(),
            'card' => $this->card,
            'pin' => $this->pin,
            'door' => $this->door,
            'event' => $this->event,
            'inOutState' => $this->inOutState,
            'isEntry' => $this->isEntry(),
            'isExit' => $this->isExit(),
            'isAccessGranted' => $this->isAccessGranted(),
            'isAccessDenied' => $this->isAccessDenied(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }
}
