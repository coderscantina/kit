<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use Kit\Reactive\Query;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `handle()` on a Query must be a pure function of its args.
 *
 * Two clients asking the same question share one computation and one stored
 * result, so a `handle()` that reads the current user or request would serve
 * one user's rows to another. It also runs on a queue worker during recompute,
 * where there is no session at all and the ambient user is simply null.
 *
 * Take what the query needs through its args and check it in `authorize()`,
 * which is per user and runs before every push.
 *
 * @implements Rule<Node\Expr>
 */
final class NoAmbientUserInQueryHandleRule implements Rule
{
    private const array STATIC = [
        'Illuminate\Support\Facades\Auth' => ['user', 'id', 'check', 'guest', 'guard'],
        'Illuminate\Support\Facades\Session' => ['get', 'all', 'put', 'has'],
        'Illuminate\Support\Facades\Request' => ['user', 'input', 'all', 'get', 'ip'],
    ];

    private const array FUNCTIONS = ['auth', 'request', 'session'];

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->insideQueryHandle($scope)) {
            return [];
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Node\Identifier) {
            $class = $scope->resolveName($node->class);
            $method = $node->name->toString();

            if (in_array($method, self::STATIC[$class] ?? [], true)) {
                return [$this->error("{$node->class->getLast()}::{$method}()")];
            }
        }

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array($node->name->toString(), self::FUNCTIONS, true)) {
            return [$this->error($node->name->toString().'()')];
        }

        return [];
    }

    private function insideQueryHandle(Scope $scope): bool
    {
        $class = $scope->getClassReflection();

        if ($class === null || ! $class->isSubclassOf(Query::class)) {
            return false;
        }

        if ($scope->getFunctionName() !== 'handle') {
            return false;
        }

        return ! str_contains($scope->getFile(), DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR);
    }

    private function error(string $call): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            "{$call} inside a Query's handle(): the result is shared by every subscriber asking the same question, and handle() also runs on a worker with no session. Take the value through the args and check it in authorize()."
        )->identifier('kit.query.ambientUser')->build();
    }
}
