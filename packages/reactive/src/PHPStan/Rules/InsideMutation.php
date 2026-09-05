<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use Kit\Reactive\Mutation;
use PHPStan\Analyser\Scope;

/**
 * Shared: is the node inside a Mutation subclass outside the package?
 */
trait InsideMutation
{
    private function insideMutation(Scope $scope): bool
    {
        $class = $scope->getClassReflection();

        if ($class === null || ! $class->isSubclassOf(Mutation::class)) {
            return false;
        }

        return ! str_contains($scope->getFile(), DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR);
    }
}
