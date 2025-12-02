<?php

namespace Ptcs\ZkTeco\Models;

class UserAuthorization
{
    public string $pin;
    public int $timezone;
    public int $doors;

    public function __construct(string $pin, int $timezone, int $doors)
    {
        $this->pin = $pin;
        $this->timezone = $timezone;
        $this->doors = $doors;
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['pin'] ?? '',
            $data['timezone'] ?? 1,
            $data['doors'] ?? 0
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'pin' => $this->pin,
            'timezone' => $this->timezone,
            'doors' => $this->doors,
        ];
    }
}
