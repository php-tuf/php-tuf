<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RequiredKeysConstraintValidator extends ConstraintValidator
{

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || [] === $value) {
            return;
        }

        if (!is_array($value)) {
            // throw this exception if your validator cannot handle the passed type so that it can be marked as invalid
            throw new UnexpectedValueException($value, 'array');
        }

        if ($missingKeys = array_flip(array_diff_key(array_flip($constraint->requiredKeys), $value))) {
            $this->context->buildViolation($constraint->message)
              ->setParameter('{{ keys }}', implode(', ', $missingKeys))
              ->addViolation();
        }
    }
}
