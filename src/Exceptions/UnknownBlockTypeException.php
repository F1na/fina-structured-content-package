<?php

namespace Fina\StructuredContent\Exceptions;

use RuntimeException;

/**
 * Thrown by BlockTypeRegistry::resolve() when the requested block type key
 * has not been registered.
 *
 * Renders as 422 rather than 404: the "type" key comes from the client's
 * request payload (store/update) and is a semantically invalid piece of
 * input, not a missing route resource. 404 is reserved for the *blockable*
 * whitelist check in BlockController::resolveBlockable(), where the route
 * segment itself doesn't correspond to any registered blockable at all.
 *
 * Implementing render() here (supported since Laravel 5.5) makes the
 * exception self-rendering and catchable "for free" — the consuming
 * application does not need to touch its own exception handler to get a
 * clean JSON response.
 */
class UnknownBlockTypeException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("Unknown block type [{$key}].");
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
