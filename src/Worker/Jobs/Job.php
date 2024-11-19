<?php

namespace FluffyDiscord\RoadRunnerBundle\Worker\Jobs;

class Job
{
    public function __construct(
        private string $queue,
        private string $payload,
        private array $headers,
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
