<?php declare(strict_types=1);

namespace Qnix\RESTful\Resolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final readonly class JsonRawResolver extends AbstractPayloadResolver implements ValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $className = (string) $argument->getType();
        $attribute = $argument->getAttributesOfType(MapRequestPayload::class)[0];

        if ($request->isMethod(Request::METHOD_GET)) {
            $item = $this->requestTransformer->transform(
                $className,
                $request->query->all(),
            );
        } else {
            $payload = $request->getContent();
            $args = (array) json_decode($payload !== '' ? $payload : '{}', true, 512, JSON_THROW_ON_ERROR);
            $item = null !== $attribute->type
                ? $this->requestTransformer->transform($attribute->type, $args, true)
                : $this->requestTransformer->transform($className, $args)
            ;
        }
        if (null === $item) {
            return [];
        }

        $this->validateObject($item, $className, $attribute);
        $request->attributes->set($argument->getName(), $item);

        return [$item];
    }
}