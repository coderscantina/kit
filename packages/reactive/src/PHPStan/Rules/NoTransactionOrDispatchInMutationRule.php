<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Mutations run inside the base class's transaction and dispatch their
 * invalidations on commit. Opening another transaction, registering
 * afterCommit hooks, or dispatching jobs by hand inside handle() breaks
 * those guarantees.
 *
 * @implements Rule<Node\Expr>
 */
final class NoTransactionOrDispatchInMutationRule implements Rule
{
    use InsideMutation;

    private const array STATIC = [
        'Illuminate\Support\Facades\DB' => ['transaction', 'beginTransaction', 'commit', 'rollBack', 'afterCommit'],
        'Illuminate\Support\Facades\Bus' => ['dispatch', 'dispatchSync', 'dispatchNow', 'dispatchAfterResponse', 'chain', 'batch'],
        'Illuminate\Support\Facades\Queue' => ['push', 'later', 'bulk', 'pushOn', 'laterOn'],
    ];

    private const array FUNCTIONS = ['dispatch', 'dispatch_sync'];

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->insideMutation($scope)) {
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

        if ($node instanceof StaticCall && $node->name instanceof Node\Identifier && $node->name->toString() === 'dispatch'
            && $node->class instanceof Name && str_ends_with($scope->resolveName($node->class), 'Job')) {
            return [$this->error($node->class->getLast().'::dispatch()')];
        }

        return [];
    }

    private function error(string $call): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            "{$call} inside a Mutation: the base class owns the transaction and dispatches invalidations on commit. See docs/reactive.md#mutations."
        )->identifier('kit.mutation.transaction')->build();
    }
}
