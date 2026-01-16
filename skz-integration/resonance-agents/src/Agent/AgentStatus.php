<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

/**
 * Enum representing the possible states of an autonomous agent
 */
enum AgentStatus: string
{
    case INITIALIZING = 'initializing';
    case ACTIVE = 'active';
    case BUSY = 'busy';
    case IDLE = 'idle';
    case ERROR = 'error';
    case SHUTDOWN = 'shutdown';

    public function isOperational(): bool
    {
        return match ($this) {
            self::ACTIVE, self::BUSY, self::IDLE => true,
            default => false,
        };
    }

    public function canProcessTasks(): bool
    {
        return match ($this) {
            self::ACTIVE, self::IDLE => true,
            default => false,
        };
    }
}
