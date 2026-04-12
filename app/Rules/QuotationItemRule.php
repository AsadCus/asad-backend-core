<?php

namespace App\Rules;

class QuotationItemRule
{
    public function rules(string $prefix = 'items')
    {
        return [
            "$prefix" => [
                'required',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $byKey = [];
                    $byId = [];

                    foreach ($value as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        if (! empty($item['_key'])) {
                            $byKey[(string) $item['_key']] = $item;
                        }

                        if (isset($item['id']) && is_numeric($item['id'])) {
                            $byId[(int) $item['id']] = $item;
                        }
                    }

                    $isTrue = static function (mixed $flag): bool {
                        return in_array($flag, [true, 1, '1', 'true'], true);
                    };

                    $hasChild = static function (array $target, array $rows): bool {
                        return collect($rows)->contains(function ($row) use ($target) {
                            if (! is_array($row)) {
                                return false;
                            }

                            return (! empty($target['_key']) && ($row['parent_key'] ?? null) === $target['_key'])
                                || (isset($target['id']) && $target['id'] !== null && (int) ($row['parent_id'] ?? 0) === (int) $target['id']);
                        });
                    };

                    foreach ($value as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $itemIsHeader = $isTrue($item['is_header'] ?? false);

                        if (! $itemIsHeader && $hasChild($item, $value)) {
                            $fail('Only header items can be parent items.');

                            return;
                        }

                        $parent = null;

                        if (! empty($item['parent_key'])) {
                            $parent = $byKey[(string) $item['parent_key']] ?? null;
                        } elseif (isset($item['parent_id']) && is_numeric($item['parent_id'])) {
                            $parent = $byId[(int) $item['parent_id']] ?? null;
                        }

                        if ($parent !== null && ! $isTrue($parent['is_header'] ?? false)) {
                            $fail('Parent item must be a header item.');

                            return;
                        }
                    }
                },
            ],
            "$prefix.*._key" => ['required', 'string'],
            "$prefix.*.id" => ['nullable'],
            "$prefix.*.customer_confirmation_member_id" => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            "$prefix.*.sharing_plan" => ['nullable', 'string', 'in:single,double,triple,quad,child_with_bed,child_no_bed,infant'],
            "$prefix.*.parent_key" => ['nullable', 'string'],
            "$prefix.*.parent_id" => ['nullable'],
            "$prefix.*.description" => ['required', 'string'],
            "$prefix.*.is_header" => ['nullable', 'boolean'],
            "$prefix.*.is_optional" => ['nullable', 'boolean'],
            "$prefix.*.quantity" => ['nullable', "required_if:$prefix.*.is_header,false", 'numeric'],
            "$prefix.*.rate" => ['nullable', "required_if:$prefix.*.is_header,false", 'numeric'],
            "$prefix.*.taxes" => ['nullable', 'array'],
            "$prefix.*.taxes.*.id" => ['nullable'],
            "$prefix.*.taxes.*.quotation_extension_master_id" => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
            "$prefix.*.taxes.*.name" => ['nullable', 'string', 'max:255'],
            "$prefix.*.taxes.*.calculation_mode" => ['nullable', 'string', 'in:fixed,percentage'],
            "$prefix.*.taxes.*.calculation_value" => ['nullable', 'numeric'],
            "$prefix.*.taxes.*.sort_order" => ['nullable', 'integer'],
            "$prefix.*.sort_order" => ['nullable', 'numeric'],
        ];
    }
}
