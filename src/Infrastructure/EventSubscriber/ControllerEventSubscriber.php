<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\EventSubscriber;

use Qnix\RESTful\Attribute as ER;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ControllerEventSubscriber implements EventSubscriberInterface
{
    private array $groupsSerialize = [];
    private array $groupsReplacement = [];
    private bool $isSerializeNull = false;

    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            [$controllerInstance, $methodName] = $controller;
        } elseif (is_object($controller)) {
            [$controllerInstance, $methodName] = [$controller, '__invoke'];
        } else {
            return;
        }

        $refClass = new \ReflectionClass($controllerInstance::class);
        $method = $refClass->getMethod($methodName);
        $methodAttribute = $method->getAttributes(ER\Groups::class)[0] ?? null;

        /** @var ER\Groups|null  $attributeInstance */
        $attributeInstance = $methodAttribute?->newInstance();

        if (null !== $attributeInstance) {
            $this->groupsSerialize = $attributeInstance->getGroupsSerialize();
            $this->groupsReplacement = $attributeInstance->getGroupsReplacement();
            $this->isSerializeNull = $attributeInstance->isSerializeNull();
        }
    }

    public function onKernelView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (
            null === $result
            || $result instanceof JsonResponse
            || !empty($this->groupsReplacement)
        ) {
            return;
        }
        if (!empty($this->groupsSerialize)) {
            $context = SerializationContext::create()
                ->setGroups($this->groupsSerialize)
                ->setSerializeNull($this->isSerializeNull)
            ;
            $result = $this->serializer->toArray($result, $context);
        }

        $event->setResponse(
            new JsonResponse(['status' => 'success', 'result' => $result]),
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 20],
            KernelEvents::VIEW => ['onKernelView', 20],
        ];
    }
}