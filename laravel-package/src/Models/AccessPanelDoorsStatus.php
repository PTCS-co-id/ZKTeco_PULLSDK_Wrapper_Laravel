<?php

namespace Ptcs\ZkTeco\Models;

class AccessPanelDoorsStatus
{
    private int $door;
    private int $alarm;

    public function __construct(string $door, string $alarm)
    {
        $this->door = (int) $door;
        $this->alarm = (int) $alarm;
    }

    /**
     * Check if door is closed
     */
    public function isDoorClosed(int $i): bool
    {
        return (($this->door >> ($i * 8)) & 255) === 1;
    }

    /**
     * Check if door is open
     */
    public function isDoorOpen(int $i): bool
    {
        return (($this->door >> ($i * 8)) & 255) === 2;
    }

    /**
     * Check if door sensor is working
     */
    public function isDoorSensorWorking(int $i): bool
    {
        return (($this->door >> ($i * 8)) & 255) !== 0;
    }

    /**
     * Check if alarm is on
     */
    public function isAlarmOn(int $i): bool
    {
        return (($this->alarm >> ($i * 8)) & 255) !== 0;
    }

    /**
     * Get status string for a specific door
     */
    public function getStatusString(int $i): string
    {
        $alarmStr = $this->isAlarmOn($i) ? ', ALARM!' : '';

        if ($this->isDoorClosed($i)) {
            return 'Closed' . $alarmStr;
        }

        if ($this->isDoorOpen($i)) {
            return 'Open' . $alarmStr;
        }

        if (!$this->isDoorSensorWorking($i)) {
            return 'Sensor Not Working' . $alarmStr;
        }

        return 'Code ' . (($this->door >> ($i * 8)) & 255) . $alarmStr;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $doors = [];
        for ($i = 0; $i < 4; $i++) {
            $doors[$i] = [
                'status' => $this->getStatusString($i),
                'isOpen' => $this->isDoorOpen($i),
                'isClosed' => $this->isDoorClosed($i),
                'sensorWorking' => $this->isDoorSensorWorking($i),
                'alarmOn' => $this->isAlarmOn($i),
            ];
        }
        return $doors;
    }
}
