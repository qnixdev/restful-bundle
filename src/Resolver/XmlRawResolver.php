<?php declare(strict_types=1);

namespace Qnix\RESTful\Resolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class XmlRawResolver extends AbstractPayloadResolver implements ValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $className = (string) $argument->getType();
        /** @var MapRequestPayload|null  $attribute */
        $attribute = $argument->getAttributesOfType(MapRequestPayload::class)[0] ?? null;

        if ($request->isMethod(Request::METHOD_GET)) {
            $item = $this->requestTransformer->transform(
                $className,
                $request->query->all(),
            );
        } else {
            $payload = simplexml_load_string($request->getContent());
            $item = $this->requestTransformer->transform(
                $className,
                (array) json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            );
        }
        if (null === $item) {
            return [];
        }

        $this->validateObject($item, $className, $attribute);
        $request->attributes->set($argument->getName(), $item);

        return [$item];
    }
}