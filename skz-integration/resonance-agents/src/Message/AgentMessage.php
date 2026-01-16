<?php

declare(strict_types=1);

namespace SKZ\Agents\Message;

/**
 * Message object for inter-agent communication
 */
readonly class AgentMessage
{
    public string $id;
    public float $timestamp;

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $senderId,
        public string $recipientId,
        public MessageType $type,
        public array $content,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        $this->id = bin2hex(random_bytes(16));
        $this->timestamp = microtime(true);
    }

    public function requiresResponse(): bool
    {
        return $this->type->requiresResponse();
    }

    public function getPriority(): int
    {
        return $this->type->getPriority();
    }

    /**
     * Create a response to this message
     *
     * @param array<string, mixed> $content
     */
    public function createResponse(string $senderId, array $content): self
    {
        return new self(
            senderId: $senderId,
            recipientId: $this->senderId,
            type: MessageType::RESPONSE,
            content: $content,
            correlationId: $this->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->senderId,
            'recipient_id' => $this->recipientId,
            'type' => $this->type->value,
            'content' => $this->content,
            'correlation_id' => $this->correlationId,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            senderId: $data['sender_id'],
            recipientId: $data['recipient_id'],
            type: MessageType::from($data['type']),
            content: $data['content'],
            correlationId: $data['correlation_id'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
