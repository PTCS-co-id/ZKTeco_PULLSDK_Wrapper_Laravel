<?php

namespace Ptcs\ZkTeco\Readers;

use Ptcs\ZkTeco\Models\UserAuthorization;

class UserAuthReader extends CsvReader
{
    private int $pinIdx = -1;
    private int $zoneIdx = -1;
    private int $doorsIdx = -1;

    public function readHead(): bool
    {
        $this->pinIdx = -1;
        $this->zoneIdx = -1;
        $this->doorsIdx = -1;

        $head = $this->nextLine();
        if ($head === null) {
            return false;
        }

        foreach ($head as $i => $field) {
            switch ($field) {
                case 'Pin':
                    $this->pinIdx = $i;
                    break;
                case 'AuthorizeTimezoneId':
                    $this->zoneIdx = $i;
                    break;
                case 'AuthorizeDoorId':
                    $this->doorsIdx = $i;
                    break;
            }
        }

        return $this->pinIdx > -1 && $this->zoneIdx > -1 && $this->doorsIdx > -1;
    }

    public function next(): ?UserAuthorization
    {
        $line = $this->nextLine();
        if ($line === null) {
            return null;
        }

        return new UserAuthorization(
            $line[$this->pinIdx],
            (int) $line[$this->zoneIdx],
            (int) $line[$this->doorsIdx]
        );
    }
}
