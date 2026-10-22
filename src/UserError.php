<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** A rule the user broke; its message is shown to them as-is. */
final class UserError extends RuntimeException
{
}
