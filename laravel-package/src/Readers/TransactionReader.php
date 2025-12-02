<?php

namespace Ptcs\ZkTeco\Readers;

use DateTime;
use Ptcs\ZkTeco\Models\Transaction;

class TransactionReader extends CsvReader
{
    public int $vmIndex = -1;
    public int $cardIndex = -1;
    public int $pinIndex = -1;
    public int $doorIndex = -1;
    public int $eventIndex = -1;
    public int $inOutStateIndex = -1;
    public int $timestampIndex = -1;

    public function readHead(): bool
    {
        $this->vmIndex = -1;
        $this->cardIndex = -1;
        $this->pinIndex = -1;
        $this->doorIndex = -1;
        $this->eventIndex = -1;
        $this->inOutStateIndex = -1;
        $this->timestampIndex = -1;

        // Cardno, Pin, Verified, DoorID, EventType, InOutState, Time_second
        $head = $this->nextLine();
        if ($head === null) {
            return false;
        }

        foreach ($head as $i => $field) {
            switch (strtolower($field)) {
                case 'cardno':
                    $this->cardIndex = $i;
                    break;
                case 'pin':
                    $this->pinIndex = $i;
                    break;
                case 'verified':
                    $this->vmIndex = $i;
                    break;
                case 'doorid':
                    $this->doorIndex = $i;
                    break;
                case 'eventtype':
                    $this->eventIndex = $i;
                    break;
                case 'inoutstate':
                    $this->inOutStateIndex = $i;
                    break;
                case 'time_second':
                    $this->timestampIndex = $i;
                    break;
            }
        }

        return $this->vmIndex > -1
            && $this->cardIndex > -1
            && $this->pinIndex > -1
            && $this->doorIndex > -1
            && $this->eventIndex > -1
            && $this->inOutStateIndex > -1
            && $this->timestampIndex > -1;
    }

    /**
     * Convert ZKTeco timestamp to DateTime
     *
     * ZKTeco uses a custom timestamp encoding where:
     * - Years are offset from 2000
     * - Each month is assumed to have 31 days
     *
     * @param int $timeCoded The encoded timestamp from ZKTeco device
     * @return DateTime The decoded datetime
     */
    private function toDateTime(int $timeCoded): DateTime
    {
        try {
            $t = $timeCoded;
            $second = $t % 60;
            $t = intdiv($t, 60);
            $minute = $t % 60;
            $t = intdiv($t, 60);
            $hour = $t % 24;
            $t = intdiv($t, 24);
            $day = 1 + ($t % 31);
            $t = intdiv($t, 31);
            $month = 1 + ($t % 12);
            $t = intdiv($t, 12);
            $year = $t + 2000;

            return new DateTime(sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second
            ));
        } catch (\Exception $e) {
            // Log invalid timestamp for debugging purposes
            error_log("Invalid ZKTeco timestamp: {$timeCoded} - {$e->getMessage()}");
            return new DateTime('1970-01-01 00:00:00');
        }
    }

    public function next(): ?Transaction
    {
        $line = $this->nextLine();
        if ($line === null) {
            return null;
        }

        return new Transaction(
            (int) $line[$this->vmIndex],
            $line[$this->cardIndex],
            $line[$this->pinIndex],
            (int) $line[$this->doorIndex],
            (int) $line[$this->eventIndex],
            (int) $line[$this->inOutStateIndex],
            $this->toDateTime((int) $line[$this->timestampIndex])
        );
    }
}
