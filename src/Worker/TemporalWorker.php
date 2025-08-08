<?php

declare(strict_types=1);

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Testing\WorkerFactory as TestingWorkerFactory;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;

class TemporalWorker implements WorkerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly array           $workers = [],
        private readonly bool            $testMode = false,
        private ?WorkerFactoryInterface  $workerFactory = null,
    )
    {
    }

    public function start(): void
    {
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        if ($this->workerFactory === null) {
            if ($this->testMode) {
                $this->workerFactory = TestingWorkerFactory::create();
            } else {
                $this->workerFactory = WorkerFactory::create();
            }
            foreach ($this->workers as $worker) {
                $newWorker = $this->workerFactory->newWorker($worker['taskQueue']);
                foreach ($worker['workflow'] as $class) {
                    $newWorker->registerWorkflowTypes($class);
                }
                foreach ($worker['activity'] as $class) {
                    $newWorker->registerActivity($class, fn(\ReflectionClass $class) => $container->get($class->getName()));
                }
            }
        }
        $this->workerFactory->run();
    }
}