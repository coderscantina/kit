<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** A URL the server will not request: not http(s), or not a public address. */
class UnsafeUrlException extends RuntimeException {}
