<?php

namespace FluffyDiscord\RoadRunnerBundle\Tests;

use FluffyDiscord\RoadRunnerBundle\Configuration\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

class ConfigurationTest extends TestCase
{
    public function testConfigurationToArray(): void
    {
        $processor = new Processor();
        $array = $processor->processConfiguration(
            new Configuration(),
            Yaml::parseFile(__DIR__ . '/dummy/fluffy_discord_road_runner.yaml')
        );
        self::assertEquals(['fffff', '33333'], $array['temporal']['workflow']);
        self::assertEquals(['aaaaa', 'qqqqq'], $array['temporal']['activity']);
        self::assertEquals('taskQueue', $array['temporal']['taskQueue']);
    }
}
