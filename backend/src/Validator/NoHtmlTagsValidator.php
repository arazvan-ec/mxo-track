<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NoHtmlTagsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoHtmlTags) {
            throw new UnexpectedTypeException($constraint, NoHtmlTags::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Check for HTML/script tags
        if ($value !== strip_tags($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
