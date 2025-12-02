<?php

namespace Ptcs\ZkTeco\Models;

class Fingerprint
{
    public string $pin;
    public int $fingerId;
    public ?string $template;
    public ?string $endTag;

    public function __construct(
        string $pin,
        int $fingerId,
        ?string $template = null,
        ?string $endTag = null
    ) {
        $this->pin = $pin;
        $this->fingerId = $fingerId;
        $this->template = $template;
        $this->endTag = $endTag;
    }

    /**
     * Get the size of the template in bytes
     */
    public function getSize(): int
    {
        if ($this->template === null) {
            return 0;
        }
        return strlen(base64_decode($this->template));
    }

    /**
     * Check if the fingerprint has a valid template
     */
    public function hasValidTemplate(): bool
    {
        return $this->template !== null && strlen($this->template) > 100;
    }

    /**
     * Compare fingerprints (for sorting)
     */
    public function compareTo(?Fingerprint $other): int
    {
        if ($other === null) {
            return -1;
        }
        $c = strcmp($this->pin ?? '', $other->pin ?? '');
        return $c === 0 ? ($this->fingerId <=> $other->fingerId) : $c;
    }

    public function __toString(): string
    {
        $size = $this->getSize();
        return sprintf(
            "Size=%d\tPin=%s\tFingerID=%d\tValid=1\tTemplate=%s\tEndTag=%s",
            $size,
            $this->pin,
            $this->fingerId,
            $this->template ?? '',
            $this->endTag ?? ''
        );
    }

    /**
     * Create fingerprint from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['pin'] ?? '',
            $data['fingerId'] ?? 0,
            $data['template'] ?? null,
            $data['endTag'] ?? null
        );
    }

    /**
     * Convert fingerprint to array
     */
    public function toArray(): array
    {
        return [
            'pin' => $this->pin,
            'fingerId' => $this->fingerId,
            'template' => $this->template,
            'endTag' => $this->endTag,
            'size' => $this->getSize(),
        ];
    }
}
