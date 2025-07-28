<?php

declare(strict_types=1);

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerFactoryInterface;

class TemporalWorker implements WorkerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?WorkerFactoryInterface $workerFactory = null,
    ) {
    }

    public function start(): void
    {
        $this->kernel->boot();
        if ($this->workerFactory === null) {
            throw new \Exception('Temporal Worker Factory not set');
        }
        $this->workerFactory->run();
    }
}
