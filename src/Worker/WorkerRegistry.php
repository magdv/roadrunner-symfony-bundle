<?php

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use FluffyDiscord\RoadRunnerBundle\Worker\Jobs\JobsWorker;
use Spiral\RoadRunner\Environment\Mode;

class WorkerRegistry
{
    public function __construct(
        private HttpWorker $httpWorker,
        private JobsWorker $jobsWorker,
    ) {
    }

    private array $workers = [];

    public function getWorker(string $mode): ?WorkerInterface
    {
        if (Mode::MODE_HTTP === $mode && !isset($this->workers[$mode])) {
            $this->workers[$mode] = $this->httpWorker;
        }

        if (Mode::MODE_JOBS === $mode && !isset($this->workers[$mode])) {
            $this->workers[$mode] = $this->jobsWorker;
        }

        return $this->workers[$mode] ?? null;
    }
}
