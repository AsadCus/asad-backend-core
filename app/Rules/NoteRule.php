<?php

namespace App\Rules;

class NoteRule
{
    public static function rules(): array
    {
        return [
            'model' => ['required', 'string'],
            'id'    => ['nullable', 'integer'],

            'notes' => ['required', 'array', 'min:1'],
            'notes.*.id' => ['nullable', 'integer'],
            'notes.*.model' => ['required', 'string'],
            'notes.*.description' => ['required', 'string'],
            'notes.*.sort_order' => ['required', 'integer'],
            'notes.*.model' => ['required_if:model,master'],
        ];
    }
}
