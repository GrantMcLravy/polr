<?php
namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Serialize model dates (toArray/toJSON, e.g. in the admin tables) as
 * "Y-m-d H:i:s", as Laravel 5.1 did. Laravel 7+ defaults to ISO 8601.
 */
trait SerializesDatesAsPolr2 {
    protected function serializeDate(DateTimeInterface $date) {
        return $date->format('Y-m-d H:i:s');
    }
}
