<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * forceFill() bypasses $fillable, which is the whole point of the strict
 * mass-assignment setting. Application code sets attributes it means to.
 *
 * @implements Rule<MethodCall>
 */
final class NoForceFillOutsidePackagesRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier || $node->name->toString() !== 'forceFill') {
            return [];
        }

        if (str_contains($scope->getFile(), DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('forceFill() outside packages/: set the attributes explicitly or add them to $fillable.')
                ->identifier('kit.model.forceFill')
                ->build(),
        ];
    }
}
