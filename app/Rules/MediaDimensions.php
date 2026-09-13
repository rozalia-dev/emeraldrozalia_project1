<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class MediaDimensions implements ValidationRule
{
    public function __construct(
        private readonly int $minimum = 64,
        private readonly int $maximum = 10000,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! str_starts_with((string) $value->getMimeType(), 'image/')) {
            return;
        }

        $dimensions = @getimagesize($value->getRealPath());
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);

        if ($width < $this->minimum || $height < $this->minimum || $width > $this->maximum || $height > $this->maximum) {
            $fail("Images must be between {$this->minimum} × {$this->minimum} and {$this->maximum} × {$this->maximum} pixels.");
        }
    }
}
