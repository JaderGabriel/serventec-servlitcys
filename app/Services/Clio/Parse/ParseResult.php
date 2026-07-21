<?php

namespace App\Services\Clio\Parse;

/**
 * @phpstan-type SchoolUpsert array{
 *   inep_code: string,
 *   name: string,
 *   dependency: ?string,
 *   collection_form: ?string,
 *   functioning_status: ?string,
 *   meta: array<string, mixed>
 * }
 */
final class ParseResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  list<string>  $warnings
     * @param  list<SchoolUpsert>  $schools
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $status,
        public readonly int $rowCount,
        public readonly ?string $code = null,
        public readonly array $warnings = [],
        public readonly array $schools = [],
        public readonly ?string $referenceDate = null,
        public readonly array $meta = [],
    ) {}

    public static function failed(string $code, string $message, array $meta = []): self
    {
        return new self(
            status: self::STATUS_FAILED,
            rowCount: 0,
            code: $code,
            warnings: [$message],
            meta: $meta,
        );
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
