<?php

namespace App\Notifications\Exceptions;

use RuntimeException;

/**
 * No active notification_templates row matches — thrown rather than ever
 * silently falling back to a hard-coded default, matching this project's
 * established stance on ambiguity/absence (see FeeResolver's exceptions).
 */
class NotificationTemplateNotFoundException extends RuntimeException
{
    public static function for(string $eventKey, string $channel, string $locale): self
    {
        return new self(
            "No active notification template found for event [{$eventKey}], channel [{$channel}], locale [{$locale}]."
        );
    }
}
