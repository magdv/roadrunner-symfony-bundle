<?php

namespace FluffyDiscord\RoadRunnerBundle\Worker\Jobs;

interface JobsRunner
{
    public function run(Job $job): void;
}
