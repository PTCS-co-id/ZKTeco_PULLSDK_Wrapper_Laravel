<?php

namespace Ptcs\ZkTeco\Readers;

abstract class CsvReader
{
    protected ?array $lines = null;
    protected int $index = 0;
    public int $lineCount = 0;

    public function __construct(string $buffer)
    {
        $this->lines = preg_split('/\r\n/', $buffer, -1, PREG_SPLIT_NO_EMPTY);
        $this->index = 0;
        $this->lineCount = count($this->lines) - 1; // -1 for header
    }

    /**
     * Get next line as array of values
     */
    protected function nextLine(): ?array
    {
        if ($this->lines === null || $this->index >= count($this->lines)) {
            return null;
        }

        $line = $this->lines[$this->index];
        if ($line === null) {
            return null;
        }

        $result = explode(',', $line);

        // Clear the line to free memory (important for large datasets)
        $this->lines[$this->index] = null;
        $this->index++;

        return $result;
    }

    /**
     * Read and parse the header line
     */
    abstract public function readHead(): bool;

    /**
     * Get the next item
     */
    abstract public function next(): mixed;
}
