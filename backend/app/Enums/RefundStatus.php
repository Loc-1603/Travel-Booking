<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case NEEDS_REVIEW = 'needs_review';

    /**
     * Statuses that represent an obligation still being fulfilled by the provider.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::PENDING->value,
            self::PROCESSING->value,
            self::SUCCEEDED->value,
        ];
    }
}
