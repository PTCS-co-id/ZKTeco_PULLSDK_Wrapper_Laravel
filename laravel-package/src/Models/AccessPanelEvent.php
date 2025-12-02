<?php

namespace Ptcs\ZkTeco\Models;

class AccessPanelEvent
{
    public ?AccessPanelDoorsStatus $doorsStatus;
    public array $events;

    public function __construct(?AccessPanelDoorsStatus $doorsStatus, array $events)
    {
        $this->doorsStatus = $doorsStatus;
        $this->events = $events;
    }

    /**
     * Get event count
     */
    public function getEventCount(): int
    {
        return count($this->events);
    }

    /**
     * Check if there are any events
     */
    public function hasEvents(): bool
    {
        return !empty($this->events);
    }

    /**
     * Check if doors status is available
     */
    public function hasDoorsStatus(): bool
    {
        return $this->doorsStatus !== null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'doorsStatus' => $this->doorsStatus?->toArray(),
            'events' => array_map(fn(AccessPanelRtEvent $e) => $e->toArray(), $this->events),
            'eventCount' => $this->getEventCount(),
        ];
    }
}
