# RoadRunner Runtime for Symfony

Yet another runtime for Symfony and [RoadRunner](https://roadrunner.dev/).

## Installation

```shell
composer require fluffydiscord/roadrunner-symfony-bundle
```

## Usage

Define the environment variable `APP_RUNTIME` in `.rr.yaml` and set up `rpc` plugin:

`.rr.yaml`
```yaml
server:
    env:
        APP_RUNTIME: FluffyDiscord\RoadRunnerBundle\Runtime\Runtime

rpc:
    listen: tcp://127.0.0.1:6001
```

Don't forget to add the `RR_RPC` to your `.env`:

```dotenv
RR_RPC=tcp://127.0.0.1:6001
```

## Configuration

`fluffy_discord_road_runner.yaml`
```yaml
fluffy_discord_road_runner:
  # Optional
  # Specify relative path from "kernel.project_dir" to your RoadRunner config file
  # if you want to run cache:warmup without having your RoadRunner running in background,
  # e.g. when building Docker images. Default is ".rr.yaml"
  rr_config_path: ".rr.yaml"
    
  # https://docs.roadrunner.dev/http/http
  http:
    # Optional
    # -----------
    # This decides when to boot the Symfony kernel.
    #
    # false (default) - before first request (worker takes some time to be ready, but app has consistent response times)
    # true - once first request arrives (worker is ready immediately, but inconsistent response times due to kernel boot time spikes)
    #
    # If you use large amount of workers, you might want to set this to true or else the RR boot up might
    # take a lot of time or just boot up using only a few "emergency" workers 
    # and then use dynamic worker scaling as described here https://docs.roadrunner.dev/php-worker/scaling
    lazy_boot: false

    # Optional
    # -----------
    # This decides if Symfony routing should be preloaded when worker starts and boots Symfony kernel.
    # This option halves the initial request response time.
    # (based on a project with over 400 routes and quite a lot of services, YMMW)
    #
    # true (default in PROD) - sends one dummy (empty) HTTP request to the kernel to initialize routing and services around it
    # false (default in DEV) - only when first worker request arrives, routing and services are loaded
    #
    # You might want to create a dummy "/" route for the route to "land",
    # or listen to onKernelRequest events and look in the request for the attribute
    # FluffyDiscord\RoadRunnerBundle\Worker\HttpWorker::DUMMY_REQUEST_ATTRIBUTE
    # Set this to "false" if you have any issues and report them to me.
    early_router_initialization: true

  # https://docs.roadrunner.dev/plugins/centrifuge
  centrifugo:
    # Optional
    # -----------
    # See http section
    lazy_boot: false

  # https://docs.roadrunner.dev/key-value/overview-kv
  kv:
    # Optional
    # -----------
    # If true (default), bundle will automatically register all "kv" adapters in your .rr.yaml.
    # Registered services have alias "cache.adapter.rr_kv.NAME"
    auto_register: true

    # Optional
    # -----------
    # Which serializer should be used.
    # By default, "IgbinarySerializer" will be used if "igbinary" php extension 
    # is installed (recommended), otherwise "DefaultSerializer".
    # You are free to create your own serializer, it needs to implement
    # Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface
    serializer: null

    # Optional
    # -----------
    # Specify relative path from "kernel.project_dir" to a keypair file 
    # for end-to-end encryption. "sodium" php extension is required. 
    # https://docs.roadrunner.dev/key-value/overview-kv#end-to-end-value-encryption
    keypair_path: bin/keypair.key

  temporal:
    workers:
      default:
        taskQueue: default
        workflow: [
            App\Modules\Workflow\GreetingWorkflow
        ]
        activity: [
            App\Modules\Workflow\GreetingActivity
        ]
```


## Running behind a load balancer or a proxy
If you want to use `REMOTE_ADDR` as trusted proxy, replace it with `0.0.0.0/0` instead 
or else your trusted headers will not work.

Symfony is using the `$_SERVER['REMOTE_ADDR']` to find out the proxy address,
but in the context of RoadRunner, `$_SERVER` contains only environment 
variables and the `REMOTE_ADDS` is missing.

## Response/file streaming

Build-in full support for Symfony's `BinaryFileResponse` and `StreamedJsonResponse`. The `StreamedResponse` needs one little 
change to be fully streamable - you have to change the `callback` to a `\Generator`, replacing all `echo` with `yield`. Look at the example:

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyController
{
    #[Route("/stream")]
    public function myStreamResponse() 
    {
        return new StreamedResponse(
            function () {
                // replace all 'echo' or any outputs with 'yield'
                // echo "data";
                yield "data";
            }
        );
    }
}
```

## Sessions

Currently, Symfony might sometimes struggle with sessions in worker mode, like loosing logged user or the opposite,
leaking logged user session to another request due to missing globals (explained at the end).

Bundle introduces `FluffyDiscord\RoadRunnerBundle\Session\WorkerSessionStorageFactory`, that handles native session correctly.
Register it manually if you happen to run into these issues, for example in `framework.yaml`:

```yaml
framework:
    session:
        storage_factory_id: FluffyDiscord\RoadRunnerBundle\Session\WorkerSessionStorageFactory
```

## Sentry

Built in support for [Sentry](https://packagist.org/packages/sentry/sentry-symfony). Just install & configure it as you normally do.

```shell
composer require sentry/sentry-symfony
```

## Centrifugo (websockets)

To enable [Centrifugo](https://github.com/centrifugal/centrifugo) you need to add `roadrunner-php/centrifugo` package.

```shell
composer require roadrunner-php/centrifugo
```

Bundle is using Symfony's Event dispatcher. You can create [event listener](https://symfony.com/doc/current/event_dispatcher.html#creating-an-event-listener) for any event extending `FluffyDiscord\RoadRunnerBundle\Event\Centrifugo\CentrifugoEventInterface`:
- `ConnectEvent` required :)
- `InvalidEvent`
- `PublishEvent`
- `RefreshEvent`
- `RPCEvent`
- `SubRefreshEvent`
- `SubscribeEvent`

Example usage:

```php
<?php

namespace App\EventListener;

use App\Centrifuge\Event\ConnectEvent;
use RoadRunner\Centrifugo\Payload\ConnectResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ConnectEvent::class, method: "handleConnect")]
readonly class ChatListener
{
    public function handleConnect(ConnectEvent $event): void
    {
        // original Centrifugo request passed from RoadRunner
        $request = $event->getRequest();
        
        // auth your user or whatever you want
        $authToken = $request->getData()["authToken"] ?? null;
        $user = ...

        // stop propagating to other listeners,
        // you have successfully connected your user
        $event->stopPropagation();

        // send response using the $event->setResponse($myResponse)
        $event->setResponse(new ConnectResponse(
            user: $user->getId(),
            data: [
                "messages" => ... // initial data client receives when connected
            ],
        ));
    }
}
```

Be aware that if you do not set any response, bundle will send `DisconnectResponse` back by default.

## Developing with Symfony and RoadRunner

- If possible, stop using lazy loading in your services, inject services immediately.
- It is no longer needed and might potentially bring issues to you like memory leaks.
- Do not use/create local class/array caches in your services. Try to make them stateless or if they cannot be,
add [ResetInterface](https://github.com/symfony/contracts/blob/main/Service/ResetInterface.php) to clean up before each request.
- Symfony forms might leak data across requests due to local caching it uses. Make sure your form `defaultOptions` are stateless. 
Do not store anything sensitive/important as it will be leaked in the following requests.
- Simplify your `User` session serialization by taking advantage of `EquatableInterface` and custom de/serialization logic. 
This will prevent errors because of detached Doctrine entities and, as a side bonus, will speed up loading user from sessions.
```php
<?php

namespace App\Entity\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $password = null;

    // serialize ony these three fields
    public function __serialize(): array
    {
        return [
            "id"       => $this->id,
            "email"    => $this->email,
            "password" => $this->password,
        ];
    }

    // unserialize ony these three fields
    public function __unserialize(array $data): void
    {
        $this->id = $data["id"] ?? null;
        $this->email = $data["email"] ?? null;
        $this->password = $data["password"] ?? null;
    }

    // check only the three serialized fields
    public function isEqualTo(mixed $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        return $this->id === $user->getId()
            &&
            $this->password === $user->getPassword()
            &&
            $this->email === $user->getEmail()
        ;
    }
}
```
## Jobs
Для обработки полученных сообщений надо eventHandler.
Любой эксепшен вернет задачу в очередь.

```php
<?php

declare(strict_types=1);

namespace App\Event\Handler;

use App\Message\NotifyToMattermostMessage;
use FluffyDiscord\RoadRunnerBundle\Event\Worker\Jobs\JobsRunEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(event: JobsRunEvent::class, method: 'handleJob')]
final class JobsEventHandler
{
    public function __construct(
        private readonly MessageBusInterface $notifyBus,
    ) {
    }

    public function handleJob(JobsRunEvent $event): void
    {
//        todo реализовать мапинг на экшены обработки разных событий
        $this->notifyBus->dispatch(
            new NotifyToMattermostMessage(
                'Получили сообщение из очереди',
                'Проверяем как работает JOBS',
                [
                    'payload' => $event->getPayload(),
                    'queue' => $event->getQueue(),
                    'headers' => $event->getHeaders(),
                ]
            )
        );
    }
}

```

## Temporal
Зарегистрируйте Workflow и Activity в конфигурации.

```yaml
    temporal:
        workers:
            default:
                taskQueue: default
                workflow: [
                    FluffyDiscord\RoadRunnerBundle\Tests\dummy\Workflow\GreetingWorkflow
                ]
                activity: [
                    FluffyDiscord\RoadRunnerBundle\Tests\dummy\Workflow\GreetingActivity
                ]
```
Главное зарегистрировать Activity как public. Иначе DI не сможет подтянуть зависимости.
```yaml
services:
    FluffyDiscord\RoadRunnerBundle\Tests\dummy\Workflow\GreetingActivity:
      public: true
```