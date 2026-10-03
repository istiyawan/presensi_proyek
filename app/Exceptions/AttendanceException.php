<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Penolakan presensi yang ditampilkan ke pengguna. `reason` dipakai
 * aplikasi mobile untuk membedakan penolakan permanen vs bisa dicoba lagi.
 */
class AttendanceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
