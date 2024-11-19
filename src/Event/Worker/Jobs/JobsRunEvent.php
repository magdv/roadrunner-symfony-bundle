<?php

namespace FluffyDiscord\RoadRunnerBundle\Event\Worker\Jobs;

use Symfony\Contracts\EventDispatcher\Event;

class JobsRunEvent extends Event
{
    public function __construct(
        private readonly string $queue,
        private readonly string $payload,
        private readonly array $headers,
    ) {
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return string[][]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
