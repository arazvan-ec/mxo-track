<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\UserPreferenceController;
use App\Dto\UpdateUserPreferencesDto;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Http\ApiErrorResponder;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(UserPreferenceController::class)]
final class UserPreferenceControllerTest extends TestCase
{
    private User $user;
    private UserPreferenceRepository $repo;
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private ApiErrorResponder $errorResponder;

    protected function setUp(): void
    {
        $this->user = new User('test@example.com');
        $this->repo = $this->createMock(UserPreferenceRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->errorResponder = new ApiErrorResponder();
    }

    private function makeController(): UserPreferenceController
    {
        return new UserPreferenceController(
            $this->repo,
            $this->em,
            $this->validator,
            $this->errorResponder,
        );
    }

    #[Test]
    public function get_returns_existing_preferences(): void
    {
        $pref = new UserPreference($this->user, 'collapsed');

        $this->repo->method('findOneByUser')->with($this->user)->willReturn($pref);

        $controller = $this->makeController();
        $response = $controller->get($this->user);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('collapsed', $data['widget_default_mode']);
    }

    #[Test]
    public function get_creates_defaults_when_no_preference_exists(): void
    {
        $this->repo->method('findOneByUser')->with($this->user)->willReturn(null);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $controller = $this->makeController();
        $response = $controller->get($this->user);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('expanded', $data['widget_default_mode']);
    }

    #[Test]
    public function update_persists_valid_change(): void
    {
        $pref = new UserPreference($this->user, 'expanded');
        $this->repo->method('findOneByUser')->with($this->user)->willReturn($pref);

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->em->expects(self::once())->method('flush');

        $controller = $this->makeController();

        $request = new Request(content: json_encode(['widget_default_mode' => 'collapsed']));
        $response = $controller->update($this->user, $request);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame('collapsed', $data['widget_default_mode']);
    }

    #[Test]
    public function update_returns_422_for_invalid_mode(): void
    {
        $pref = new UserPreference($this->user, 'expanded');
        $this->repo->method('findOneByUser')->with($this->user)->willReturn($pref);

        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation->method('getPropertyPath')->willReturn('widgetDefaultMode');
        $violation->method('getMessage')->willReturn('Invalid value.');

        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $controller = $this->makeController();

        $request = new Request(content: json_encode(['widget_default_mode' => 'invalid']));
        $response = $controller->update($this->user, $request);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function update_returns_400_for_invalid_json(): void
    {
        $controller = $this->makeController();

        $request = new Request(content: 'not json');
        $response = $controller->update($this->user, $request);

        self::assertSame(400, $response->getStatusCode());
    }
}
