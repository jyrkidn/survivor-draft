<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for every domain failure the three surfaces share.
 *
 * The message is always plain English addressed to a person: MCP tools hand it
 * straight back to the model as a tool result, and Filament shows it in a
 * notification. Nothing here should mention a class name or a column.
 */
abstract class LeagueException extends RuntimeException {}
