<?php

declare(strict_types=1);

namespace Suisen\Infra;

use RuntimeException;

/** メールを送れなかった。 */
final class MailException extends RuntimeException
{
}
