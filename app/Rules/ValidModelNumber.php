<?php

namespace App\Rules;

use App\Services\NumberingService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

class ValidModelNumber implements ValidationRule
{
    public function __construct(
        private string $modelKey,
        private ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        try {
            app(NumberingService::class)->validateProvidedNumber(
                $this->modelKey,
                trim((string) $value),
                $this->ignoreId,
            );
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            $firstMessage = collect($messages)->flatten()->first();

            $fail((string) ($firstMessage ?? 'The number is invalid.'));
        }
    }
}
