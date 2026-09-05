<?php

declare(strict_types=1);

namespace Kit\Reactive\PHPStan\Rules;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A queued broadcast is encoded on the worker, where a serialized model is
 * re-fetched without the request's context. Resolve the payload to arrays
 * in the constructor instead (see ResolvesBroadcastPayload).
 *
 * @implements Rule<InClassNode>
 */
final class NoSerializesModelsOnBroadcastEventRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();

        if (! $class->implementsInterface(ShouldBroadcast::class)) {
            return [];
        }

        foreach ($class->getTraits(true) as $trait) {
            if ($trait->getName() === SerializesModels::class) {
                return [
                    RuleErrorBuilder::message(
                        "{$class->getName()} broadcasts and uses SerializesModels. Resolve the payload to plain arrays in the constructor instead."
                    )->identifier('kit.broadcast.serializesModels')->build(),
                ];
            }
        }

        return [];
    }
}
