<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Packs an IPv4/IPv6 string into the raw bytes a VARBINARY(16) column stores
 * (the same representation MySQL/MariaDB's INET6_ATON produces), so columns
 * like login_attempts.ip_address and users.last_login_ip hold compact binary
 * addresses on disk while the model attribute is always a readable string.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class IpAddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $address = inet_ntop($value);

        if ($address === false) {
            throw new InvalidArgumentException("Stored value for {$key} is not valid packed binary IP data.");
        }

        return $address;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $packed = inet_pton($value);

        if ($packed === false) {
            throw new InvalidArgumentException("Invalid IP address: {$value}");
        }

        return $packed;
    }
}
