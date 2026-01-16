<?php

declare(strict_types=1);

namespace SKZ\Agents\Message;

/**
 * Types of messages exchanged between agents
 */
enum MessageType: string
{
    case COMMAND = 'command';
    case QUERY = 'query';
    case EVENT = 'event';
    case COORDINATION = 'coordination';
    case RESPONSE = 'response';
    case BROADCAST = 'broadcast';
    case HEARTBEAT = 'heartbeat';

    public function requiresResponse(): bool
    {
        return match ($this) {
            self::QUERY, self::COMMAND => true,
            default => false,
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::HEARTBEAT => 0,
            self::BROADCAST => 1,
            self::EVENT => 2,
            self::RESPONSE => 3,
            self::QUERY => 4,
            self::COORDINATION => 5,
            self::COMMAND => 6,
        };
    }
}
