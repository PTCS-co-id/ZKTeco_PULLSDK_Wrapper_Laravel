<?php

namespace Ptcs\ZkTeco\Readers;

use Ptcs\ZkTeco\Models\Fingerprint;

class FpReader extends CsvReader
{
    private int $pinIndex = -1;
    private int $fidIndex = -1;
    private int $templateIndex = -1;
    private int $etagIndex = -1;

    public function readHead(): bool
    {
        $this->pinIndex = -1;
        $this->fidIndex = -1;
        $this->templateIndex = -1;
        $this->etagIndex = -1;

        $head = $this->nextLine();
        if ($head === null) {
            return false;
        }

        foreach ($head as $i => $field) {
            switch ($field) {
                case 'Pin':
                    $this->pinIndex = $i;
                    break;
                case 'FingerID':
                    $this->fidIndex = $i;
                    break;
                case 'Template':
                    $this->templateIndex = $i;
                    break;
                case 'EndTag':
                    $this->etagIndex = $i;
                    break;
            }
        }

        return $this->etagIndex > -1 && $this->pinIndex > -1 && $this->fidIndex > -1;
    }

    public function next(): ?Fingerprint
    {
        $line = $this->nextLine();
        if ($line === null) {
            return null;
        }

        return new Fingerprint(
            $line[$this->pinIndex],
            (int) $line[$this->fidIndex],
            $this->templateIndex > -1 ? ($line[$this->templateIndex] ?? null) : null,
            $line[$this->etagIndex] ?? null
        );
    }
}
