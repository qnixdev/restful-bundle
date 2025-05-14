<?php declare(strict_types=1);

namespace Qnix\RESTful\Infrastructure\EventListener;

use Qnix\RESTful\Infrastructure\Transformer\Exception as RestException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException', priority: 250)]
readonly class ExceptionEventListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $this->convertExceptionToArrayFormat($event->getThrowable());

        if (!empty($exception)) {
            $event->setResponse(new JsonResponse($exception, Response::HTTP_UNPROCESSABLE_ENTITY));
        }
    }

    private function convertExceptionToArrayFormat(\Throwable $ex): array
    {
        if ($ex instanceof RestException\ApiMissingFieldException) {
            return $this->formatArray(
                slug: $ex->getMessage(),
                message: $ex->getMessage(),
            );
        }
        if ($ex instanceof RestException\ApiValidationFieldException) {
            $violations = [];

            foreach ($ex->getErrors() as $errorData) {
                foreach ($errorData as $err) {
                    $violations[] = [
                        'parameter' => $err['parameter'],
                        'error' => $err['error'],
                    ];
                }
            }

            return $this->formatArray(
                slug: $ex->getMessage(),
                message: '',
                violations: $violations,
            );
        }
        if ($ex instanceof RestException\ApiWrongDataException) {
            return $this->formatArray(
                slug: $ex->getMessage(),
                message: $ex->getMessage(),
            );
        }

        return [];
    }

    private function formatArray(
        string $slug,
        string $message,
        array $violations = [],
    ): array {
        $error = ['error' => $slug];

        if ($message !== '') {
            $error['message'] = $message;
        }
        if (count($violations) > 0) {
            $error['details'] = $violations;
        }

        return $error;
    }
}