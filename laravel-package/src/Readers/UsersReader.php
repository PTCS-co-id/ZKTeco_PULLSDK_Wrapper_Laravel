<?php

namespace Ptcs\ZkTeco\Readers;

use Ptcs\ZkTeco\Models\User;

class UsersReader extends CsvReader
{
    private int $cardIndex = -1;
    private int $nameIndex = -1;
    private int $startDateIndex = -1;
    private int $endDateIndex = -1;
    private int $passIndex = -1;
    private int $pinIndex = -1;

    public function readHead(): bool
    {
        $this->nameIndex = -1;
        $this->startDateIndex = -1;
        $this->endDateIndex = -1;
        $this->passIndex = -1;
        $this->pinIndex = -1;
        $this->cardIndex = -1;

        $head = $this->nextLine();
        if ($head === null) {
            return false;
        }

        foreach ($head as $i => $field) {
            switch ($field) {
                // CardNo,Pin,Password,Group,StartTime,EndTime,SuperAuthorize
                case 'CardNo':
                    $this->cardIndex = $i;
                    break;
                case 'Pin':
                    $this->pinIndex = $i;
                    break;
                case 'Name':
                    $this->nameIndex = $i; // old devices don't have this
                    break;
                case 'Password':
                    $this->passIndex = $i;
                    break;
                case 'StartTime':
                    $this->startDateIndex = $i;
                    break;
                case 'EndTime':
                    $this->endDateIndex = $i;
                    break;
            }
        }

        return $this->cardIndex > -1
            && $this->startDateIndex > -1
            && $this->endDateIndex > -1
            && $this->passIndex > -1
            && $this->pinIndex > -1;
    }

    public function next(): ?User
    {
        $line = $this->nextLine();
        if ($line === null) {
            return null;
        }

        return new User(
            $line[$this->pinIndex],
            $this->nameIndex > -1 ? ($line[$this->nameIndex] ?? '') : '',
            $line[$this->cardIndex],
            $line[$this->passIndex],
            $line[$this->startDateIndex],
            $line[$this->endDateIndex]
        );
    }
}
