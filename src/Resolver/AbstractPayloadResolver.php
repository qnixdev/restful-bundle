<?php declare(strict_types=1);

namespace Qnix\RESTful\Resolver;

use Qnix\RESTful\Infrastructure\Transformer\Exception as RestException;
use Qnix\RESTful\Infrastructure\Transformer\RequestTransformer;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract readonly class AbstractPayloadResolver
{
    public function __construct(
        protected RequestTransformer $requestTransformer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * @throws RestException\ApiValidationFieldException
     */
    protected function validateObject(object|array $item, string $className, MapRequestPayload $payload): void
    {
        $violations = $this->validator->validate(
            $item,
            groups: $payload->validationGroups ?? [],
        );

        if ($violations->count() > 0) {
            $normalizer = (string) preg_replace(['/Exception/', '/(?<!\s)[A-Z]/'], ['', '_$0'], $className);
            $normalizer = strtoupper(substr($normalizer, 1));
            $errorList = [];

            /** @var ConstraintViolation  $violation */
            foreach ($violations as $violation) {
                $errorList[$normalizer][] = [
                    'parameter' => $violation->getPropertyPath(),
                    'error' => $violation->getMessage(),
                ];
            }

            throw new RestException\ApiValidationFieldException($errorList);
        }
    }
}