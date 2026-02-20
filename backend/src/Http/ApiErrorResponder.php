<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiErrorResponder
{
    public function badRequest(string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 400);
    }

    public function unprocessableEntity(string $code, ConstraintViolationListInterface $violations): JsonResponse
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[] = [
                'field' => (string) $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => 'Payload inválido.',
                'details' => $details,
            ],
        ], 422);
    }

    public function notFound(string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 404);
    }
}
