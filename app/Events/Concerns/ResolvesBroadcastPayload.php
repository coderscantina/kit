<?php

declare(strict_types=1);

namespace App\Events\Concerns;

use JsonSerializable;

/**
 * Fully materialise a payload into plain data in the event constructor.
 *
 * A queued broadcast is encoded on the worker, where the request's ambient
 * context is gone and a live model or nested resource can no longer be read.
 * Data::toArray() and JsonResource::resolve() only expand the outermost
 * layer; a full JSON round-trip here flattens everything while the request
 * is still active. Decoded as objects so an empty `{}` stays `{}` on the wire.
 *
 * @phpstan-ignore trait.unused
 */
trait ResolvesBroadcastPayload
{
    /**
     * @param  JsonSerializable|array<mixed>  $payload
     * @return array<string, mixed>
     */
    protected function resolveBroadcastPayload(JsonSerializable|array $payload): array
    {
        return (array) json_decode(
            json_encode($payload, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
