<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** The server isn't set up the way the app needs (e.g. data/ not writable); the message says how to fix it. */
final class SetupError extends RuntimeException
{
}
