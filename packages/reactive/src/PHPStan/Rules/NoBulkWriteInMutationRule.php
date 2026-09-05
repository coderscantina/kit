<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Writes that bypass model events are invisible to reactive invalidation:
 * DB::table()->update(), raw statements, Model::query()->update(). Use
 * Model::withoutReactiveEvents() plus an explicit Invalidate::table().
 *
 * @implements Rule<Node\Expr>
 */
final class NoBulkWriteInMutationRule implements Rule
{
    use InsideMutation;

    private const array DB_METHODS = ['table', 'statement', 'unprepared', 'update', 'insert', 'delete', 'affectingStatement'];

    private const array BUILDER_WRITES = ['update', 'delete', 'insert', 'insertOrIgnore', 'upsert', 'increment', 'decrement', 'forceDelete', 'insertGetId', 'truncate'];

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->insideMutation($scope)) {
            return [];
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Node\Identifier
            && $scope->resolveName($node->class) === 'Illuminate\Support\Facades\DB'
            && in_array($node->name->toString(), self::DB_METHODS, true)) {
            return [$this->error('DB::'.$node->name->toString().'()')];
        }

        if ($node instanceof MethodCall && $node->name instanceof Node\Identifier && in_array($node->name->toString(), self::BUILDER_WRITES, true)) {
            $type = $scope->getType($node->var);

            foreach ([EloquentBuilder::class, QueryBuilder::class, Relation::class] as $builder) {
                if ((new ObjectType($builder))->isSuperTypeOf($type)->yes()) {
                    return [$this->error('->'.$node->name->toString().'() on a query builder')];
                }
            }
        }

        return [];
    }

    private function error(string $call): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            "{$call} inside a Mutation is invisible to reactive invalidation. Write through models, or use withoutReactiveEvents() and Invalidate::table(). See docs/limitations.md#non-eloquent-writes-are-invisible."
        )->identifier('kit.mutation.bulkWrite')->build();
    }
}
