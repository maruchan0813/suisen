<?php

declare(strict_types=1);

namespace Suisen\Infra;

use RuntimeException;

/** 学内 LLM から使える返事をもらえなかった。 */
final class LlmUnavailableException extends RuntimeException
{
}
