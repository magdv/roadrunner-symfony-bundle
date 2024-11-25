<?php

declare(strict_types=1);

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;

class TemporalWorker implements WorkerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly string $taskQueue = 'taskQueue',
        private array $workflowClassNames = [],
        private array $activityClassNames = [],
        private readonly ?WorkerOptions $workerOptions = null,
    ) {
    }

    public function start(): void
    {
        $this->kernel->boot();

        $factory = WorkerFactory::create();

        // Worker that listens on a task queue and hosts both workflow and activity implementations.
        $worker = $factory->newWorker(
            $this->taskQueue,
            $this->workerOptions
        );

        foreach ($this->workflowClassNames as $class) {
            $worker->registerWorkflowTypes($class);
        }

        foreach ($this->activityClassNames as $class) {
            $worker->registerActivityImplementations(new $class());
        }

        $factory->run();
    }
}
