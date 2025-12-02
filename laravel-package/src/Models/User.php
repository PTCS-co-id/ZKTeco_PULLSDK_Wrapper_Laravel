<?php

namespace Ptcs\ZkTeco\Models;

class User
{
    public string $pin;
    public string $card;
    public string $name;
    public string $password;
    public string $startTime;
    public string $endTime;
    public array $doors = [];
    public array $fingerprints = [];

    public function __construct(
        string $pin,
        string $name = '',
        string $card = '',
        string $password = '',
        string $startTime = '',
        string $endTime = '',
        array $doors = []
    ) {
        $this->pin = $pin;
        $this->name = $name;
        $this->card = $card;
        $this->password = $password;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->doors = $doors;
    }

    /**
     * Set doors by flag (bit representation)
     * Every bit in flag represents a door
     */
    public function setDoorsByFlag(int $flag): void
    {
        $doors = [];
        for ($i = 0; $i < 16; $i++) {
            $bit = 1 << $i;
            if (($flag & $bit) !== 0) {
                $doors[] = $i + 1;
            }
        }
        $this->doors = $doors;
    }

    /**
     * Get doors as a flag (bit representation)
     */
    public function getDoorsFlag(): int
    {
        $flag = 0;
        foreach ($this->doors as $door) {
            $flag |= (1 << ($door - 1));
        }
        return $flag;
    }

    /**
     * Add a fingerprint to the user
     */
    public function addFingerprint(Fingerprint $fingerprint): void
    {
        if (count($this->fingerprints) >= 10) {
            throw new \OutOfRangeException('A user can only have 10 fingerprints');
        }
        $this->fingerprints[] = $fingerprint;
    }

    /**
     * Remove a fingerprint by finger index
     */
    public function removeFingerprint(int $fingerIndex): void
    {
        $this->fingerprints = array_filter(
            $this->fingerprints,
            fn(Fingerprint $fp) => $fp->fingerId !== $fingerIndex
        );
        $this->fingerprints = array_values($this->fingerprints);
    }

    /**
     * Check if user has fingerprint for specific finger
     */
    public function hasFingerprint(int $finger): bool
    {
        foreach ($this->fingerprints as $fp) {
            if ($fp->fingerId === $finger) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if two users are equal
     */
    public function equals(User $other): bool
    {
        return $this->name === $other->name
            && $this->password === $other->password
            && $this->pin === $other->pin
            && $this->card === $other->card
            && $this->startTime === $other->startTime
            && $this->endTime === $other->endTime;
    }

    /**
     * Compare users by PIN (for sorting)
     */
    public function compareTo(?User $other): int
    {
        if ($other === null) {
            return 1;
        }
        return strcmp($this->pin, $other->pin);
    }

    /**
     * Convert to 2018 format string
     */
    public function to2018Format(): string
    {
        return sprintf(
            "CardNo=%s\tPin=%s\tName=%s\tPassword=%s\tStartTime=%s\tEndTime=%s",
            $this->card,
            $this->pin,
            $this->name,
            $this->password,
            $this->startTime,
            $this->endTime
        );
    }

    /**
     * Convert to 2014 format string (no name field)
     */
    public function to2014Format(): string
    {
        return sprintf(
            "CardNo=%s\tPin=%s\tPassword=%s\tStartTime=%s\tEndTime=%s",
            $this->card,
            $this->pin,
            $this->password,
            $this->startTime,
            $this->endTime
        );
    }

    public function __toString(): string
    {
        return $this->to2018Format();
    }

    /**
     * Create user from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['pin'] ?? '',
            $data['name'] ?? '',
            $data['card'] ?? '',
            $data['password'] ?? '',
            $data['startTime'] ?? '',
            $data['endTime'] ?? '',
            $data['doors'] ?? []
        );
    }

    /**
     * Convert user to array
     */
    public function toArray(): array
    {
        return [
            'pin' => $this->pin,
            'name' => $this->name,
            'card' => $this->card,
            'password' => $this->password,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'doors' => $this->doors,
            'fingerprints' => array_map(fn(Fingerprint $fp) => $fp->toArray(), $this->fingerprints),
        ];
    }
}
