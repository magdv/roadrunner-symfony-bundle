<?php

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use FluffyDiscord\RoadRunnerBundle\Event\Worker\Jobs\JobsRunEvent;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

class JobsWorker implements WorkerInterface
{
    private ?Consumer $consumer = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function start(): void
    {
        $this->kernel->boot();
        $shouldBeRestarted = false;

        $i = 0;
        /** @var ReceivedTaskInterface $task */
        while ($task = $this->waitTask()) {
            try {
                $this->eventDispatcher->dispatch(
                    new JobsRunEvent(
                        $task->getQueue(),
                        $task->getPayload(),
                        $task->getHeaders()
                    )
                );

                // сборка мусора каждые 20 запросов
                ++$i;
                if ($i === 20) {
                    gc_collect_cycles();
                    $i = 0;
                }

                $task->ack();
            } catch (Throwable $throwable) {
                $task->nack($throwable, $shouldBeRestarted);
            }
        }
    }

    private function waitTask(): ?ReceivedTaskInterface
    {
        if (!$this->consumer instanceof Consumer) {
            $this->consumer = new Consumer();
        }

        return $this->consumer->waitTask();
    }
}
