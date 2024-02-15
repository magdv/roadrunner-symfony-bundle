# RoadRunner Runtime for Symfony

Yet another runtime for Symfony and [RoadRunner](https://roadrunner.dev/).

## Installation

```shell
composer require fluffydiscord/roadrunner-symfony-bundle
```

## Usage

Define the environment variable `APP_RUNTIME` in `.rr.yaml`

```
// .rr.yaml
server:
    ...
    env:
        APP_RUNTIME: FluffyDiscord\RoadRunnerBundle\Runtime\Runtime
```

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

## Sentry

Built in support for [Sentry](https://packagist.org/packages/sentry/sentry-symfony). Just install it as you normally do.

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

## Debugging (recommendations)

With RoadRunner you cannot simply dump and die, because nothing will be printed.
I would like to introduce [Buggregator](https://docs.buggregator.dev/config/var-dumper.html) to work around that. 
As a bonus it can also work as a [mailtrap](https://docs.buggregator.dev/config/smtp.html) or testing [Sentry](https://docs.buggregator.dev/config/sentry.html) locally

## Credits

Inspiration taken from existing solutions like [Baldinof's Bundle](https://github.com/Baldinof/roadrunner-bundle) 
and [Nyholm's Runtime](https://github.com/php-runtime/roadrunner-symfony-nyholm)
