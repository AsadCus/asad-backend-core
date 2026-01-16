<?php

namespace App\Services;

use App\Models\EducationLevel;

class EducationLevelService
{
    public function get()
    {
        $data = EducationLevel::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = EducationLevel::get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilter()
    {
        $data = EducationLevel::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilterByName()
    {
        $data = EducationLevel::get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => $q->name,
            ];
        });

        return $data;
    }
}
