<?php

declare(strict_types=1);

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;

class TemporalWorker implements WorkerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly array $workers = [],
        private ?WorkerFactoryInterface $workerFactory = null,
    ) {
    }

    public function start(): void
    {
        $this->kernel->boot();
        if ($this->workerFactory === null) {
            $this->workerFactory = WorkerFactory::create();
            foreach ($this->workers as $worker) {
                $newWorker = $this->workerFactory->newWorker($worker['taskQueue']);
                foreach ($worker['workflow'] as $class) {
                    $newWorker->registerWorkflowTypes($class);
                }
                foreach ($worker['activity'] as $class) {
                    $newWorker->registerActivity($class);
                }
            }
        }
        $this->workerFactory->run();
    }
}