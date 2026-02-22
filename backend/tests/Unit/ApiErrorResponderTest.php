<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\ApiErrorResponder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(ApiErrorResponder::class)]
final class ApiErrorResponderTest extends TestCase
{
    private ApiErrorResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new ApiErrorResponder();
    }

    #[Test]
    public function badRequestReturns400(): void
    {
        $response = $this->responder->badRequest('invalid_input', 'The input is invalid.');

        self::assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('invalid_input', $data['error']['code']);
        self::assertSame('The input is invalid.', $data['error']['message']);
    }

    #[Test]
    public function notFoundReturns404(): void
    {
        $response = $this->responder->notFound('entity_not_found', 'Entity was not found.');

        self::assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('entity_not_found', $data['error']['code']);
        self::assertSame('Entity was not found.', $data['error']['message']);
    }

    #[Test]
    public function unprocessableEntityReturns422WithDetails(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This field is required.', null, [], null, 'name', null),
            new ConstraintViolation('Must be a valid email.', null, [], null, 'email', null),
        ]);

        $response = $this->responder->unprocessableEntity('validation_failed', $violations);

        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('validation_failed', $data['error']['code']);
        self::assertCount(2, $data['error']['details']);
        self::assertSame('name', $data['error']['details'][0]['field']);
        self::assertSame('This field is required.', $data['error']['details'][0]['message']);
        self::assertSame('email', $data['error']['details'][1]['field']);
    }

    #[Test]
    public function unprocessableEntityWithNoViolationsHasEmptyDetails(): void
    {
        $violations = new ConstraintViolationList();

        $response = $this->responder->unprocessableEntity('validation_failed', $violations);

        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertEmpty($data['error']['details']);
    }

    #[Test]
    public function allResponsesReturnValidJson(): void
    {
        $badRequest = $this->responder->badRequest('code', 'message');
        $notFound = $this->responder->notFound('code', 'message');
        $unprocessable = $this->responder->unprocessableEntity('code', new ConstraintViolationList());

        foreach ([$badRequest, $notFound, $unprocessable] as $response) {
            $decoded = json_decode($response->getContent(), true);
            self::assertIsArray($decoded);
            self::assertArrayHasKey('error', $decoded);
            self::assertArrayHasKey('code', $decoded['error']);
        }
    }
}
