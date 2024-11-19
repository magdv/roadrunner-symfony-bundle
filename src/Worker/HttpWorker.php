<?php

namespace FluffyDiscord\RoadRunnerBundle\Worker;

use FluffyDiscord\RoadRunnerBundle\Event\Worker\WorkerBootingEvent;
use FluffyDiscord\RoadRunnerBundle\Factory\BinaryFileResponseWrapper;
use FluffyDiscord\RoadRunnerBundle\Factory\DefaultResponseWrapper;
use FluffyDiscord\RoadRunnerBundle\Factory\StreamedJsonResponseWrapper;
use FluffyDiscord\RoadRunnerBundle\Factory\StreamedResponseWrapper;
use GuzzleHttp\Promise\PromiseInterface; // Sentry v4 compatibility
use Nyholm\Psr7;
use Sentry\State\HubInterface as SentryHubInterface;
use Spiral\RoadRunner;
use Spiral\RoadRunner\Environment;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

readonly class HttpWorker implements WorkerInterface
{
    /**
     * @var string
     */
    public const DUMMY_REQUEST_ATTRIBUTE = "rr_dummy_request";

    public function __construct(
        private KernelInterface $kernel,
        private EventDispatcherInterface $eventDispatcher,
        private ?SentryHubInterface $sentryHubInterface = null,
    ) {
    }

    public function start(): void
    {
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr7\Factory\Psr17Factory();

        $worker = new RoadRunner\Http\PSR7Worker(
            RoadRunner\Worker::createFromEnvironment(
                Environment::fromGlobals()
            ),
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
        );

            $this->kernel->boot();

            // Initialize routing and other lazy services that Symfony has.
            // Reduces first real request response time more than 50%, YMMW
            $this->kernel->handle(new Request(attributes: [self::DUMMY_REQUEST_ATTRIBUTE => true]));

            // Preload reflections, up to 2ms savings for each, YMMW
            new \ReflectionClass(StreamedJsonResponse::class);
            new \ReflectionClass(StreamedResponse::class);
            new \ReflectionClass(BinaryFileResponse::class);

        $this->eventDispatcher->dispatch(new WorkerBootingEvent());

        try {
            $i = 0;
            while ($request = $worker->waitRequest()) {
                $this->sentryHubInterface?->pushScope();

                try {
                    $symfonyRequest = $httpFoundationFactory->createRequest($request);
                    $symfonyResponse = $this->kernel->handle($symfonyRequest);

                    $content = match (true) {
                        $symfonyResponse instanceof StreamedJsonResponse => StreamedJsonResponseWrapper::wrap($symfonyResponse),
                        $symfonyResponse instanceof StreamedResponse => StreamedResponseWrapper::wrap($symfonyResponse),
                        $symfonyResponse instanceof BinaryFileResponse => BinaryFileResponseWrapper::wrap($symfonyResponse, $symfonyRequest),
                        default => DefaultResponseWrapper::wrap($symfonyResponse),
                    };

                    $worker->getHttpWorker()->respond(
                        $symfonyResponse->getStatusCode(),
                        $content,
                        $symfonyResponse->headers->all(),
                    );

                    if ($this->kernel instanceof TerminableInterface) {
                        $this->kernel->terminate($symfonyRequest, $symfonyResponse);
                    }
                } catch (\Throwable $throwable) {
                    $this->sentryHubInterface?->captureException($throwable);
                    $worker->getWorker()->error((string)$throwable);
                } finally {
                    $result = $this->sentryHubInterface?->getClient()?->flush();

                    // sentry v4 compatibility
                    if ($result instanceof PromiseInterface) {
                        $result->wait(false);
                    }

                    $this->sentryHubInterface?->popScope();
                }

                // сборка мусора каждые 20 запросов
                ++$i;
                if ($i === 20) {
                    gc_collect_cycles();
                    $i = 0;
                }
            }
        } catch (\Throwable $throwable) {
            $worker->getWorker()->stop();
            throw $throwable;
        }
    }
}
