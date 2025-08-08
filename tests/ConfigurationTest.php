<?php

namespace FluffyDiscord\RoadRunnerBundle\Tests;

use FluffyDiscord\RoadRunnerBundle\Configuration\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;
use Temporal\WorkerFactory;

class ConfigurationTest extends TestCase
{
    public function testConfigurationToArray(): void
    {
        $processor = new Processor();
        $array = $processor->processConfiguration(
            new Configuration(),
            Yaml::parseFile(__DIR__ . '/dummy/fluffy_discord_road_runner.yaml')
        );
        self::assertEquals([
            'default' => [
                'taskQueue' => 'default',
                'workflow'  => [
                    'FluffyDiscord\RoadRunnerBundle\Tests\dummy\Workflow\GreetingWorkflow'
                ],
                'activity'  => [
                    'FluffyDiscord\RoadRunnerBundle\Tests\dummy\Workflow\GreetingActivity'
                ],
            ]
        ], $array['temporal']['workers']);
        self::assertFalse($array['temporal']['testMode']);

        $factory = WorkerFactory::create();

        foreach ($array['temporal']['workers'] as $worker) {
            // Worker that listens on a task queue and hosts both workflow and activity implementations.
            $worker = $factory->newWorker(
                $worker['taskQueue']
            );

            foreach ($array['temporal']['workflow'] as $class) {
                $worker->registerWorkflowTypes($class);
            }

            foreach ($array['temporal']['activity'] as $class) {
                $worker->registerActivityImplementations(new $class());
            }
        }
    }
}
