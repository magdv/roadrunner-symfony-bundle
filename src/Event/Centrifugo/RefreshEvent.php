<?php

namespace FluffyDiscord\RoadRunnerBundle\Event\Centrifugo;

use RoadRunner\Centrifugo\Payload\RefreshResponse;
use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Request\Refresh;
use Symfony\Contracts\EventDispatcher\Event;

class RefreshEvent extends Event implements CentrifugoEventInterface
{
    private ?RefreshResponse $response = null;

    public function __construct(
        private readonly Refresh $request,
    ) {
    }

    public function getRequest(): Refresh
    {
        return $this->request;
    }

    public function getResponse(): ?RefreshResponse
    {
        return $this->response;
    }

    public function setResponse(RefreshResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;
        return $this;
    }
}
