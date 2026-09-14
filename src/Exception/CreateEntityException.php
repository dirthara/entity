<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use \ReflectionException;

final class CreateEntityException extends EntityException
{
    public static function fromReflection(ReflectionException $exception, string $entity): self
    {
        return new self(
            message: sprintf('Failed to create entity "%s": %s', $entity, $exception->getMessage()),
            previous: $exception,
        )->addContext([
            'entity' => $entity,
        ]);
    }
}
