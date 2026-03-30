<?php

namespace App\Rules;

class OpsMovementRule
{
    public function rules(): array
    {
        return [
            'ops_base' => ['nullable', 'string', 'max:255'],
            'infotech_ref' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'doa_by' => ['nullable', 'string', 'max:255'],
            'doa_datetime' => ['nullable', 'date'],
            'train_description' => ['nullable', 'string'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'vehicle_driver_name' => ['nullable', 'string', 'max:255'],
            'vehicle_driver_contact_number' => ['nullable', 'string', 'max:255'],
            'visa_submitted_to_z_umrah' => ['nullable', 'boolean'],
            'visa_approved' => ['nullable', 'boolean'],

            'accommodations' => ['nullable', 'array'],
            'accommodations.*.id' => ['required', 'integer', 'exists:package_accommodations,id'],
            'accommodations.*.ic' => ['nullable', 'string', 'max:255'],

            'officials' => ['nullable', 'array'],
            'officials.*.id' => ['required', 'integer', 'exists:package_officials,id'],
            'officials.*.hotel' => ['nullable', 'string', 'max:255'],
            'officials.*.hotels_by_location' => ['nullable', 'array'],
            'officials.*.hotels_by_location.*.location' => ['nullable', 'string', 'max:255'],
            'officials.*.hotels_by_location.*.hotel' => ['nullable', 'string', 'max:255'],

            'flights' => ['nullable', 'array'],
            'flights.*.id' => ['required', 'integer', 'exists:package_flights,id'],
            'flights.*.ic' => ['nullable', 'string', 'max:255'],

            'documents' => ['nullable', 'array'],
            'documents.itinerary' => ['nullable', 'array'],
            'documents.itinerary.*.id' => ['nullable', 'integer', 'exists:model_files,id'],
            'documents.itinerary.*.file' => ['nullable', 'file', 'max:10240'],
            'documents.itinerary.*.file_name' => ['nullable', 'string', 'max:255'],
            'documents.itinerary.*.file_path' => ['nullable', 'string', 'max:255'],
            'documents.itinerary.*.removed' => ['nullable', 'boolean'],
            'documents.booklet' => ['nullable', 'array'],
            'documents.booklet.*.id' => ['nullable', 'integer', 'exists:model_files,id'],
            'documents.booklet.*.file' => ['nullable', 'file', 'max:10240'],
            'documents.booklet.*.file_name' => ['nullable', 'string', 'max:255'],
            'documents.booklet.*.file_path' => ['nullable', 'string', 'max:255'],
            'documents.booklet.*.removed' => ['nullable', 'boolean'],

            'budget' => ['nullable', 'array'],
            'budget.*.title' => ['nullable', 'string', 'max:255'],
            'budget.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'budget.*.items' => ['nullable', 'array'],
            'budget.*.items.*.item_name' => ['nullable', 'string', 'max:255'],
            'budget.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'budget.*.items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'budget.*.items.*.remarks' => ['nullable', 'string'],
            'budget.*.items.*.sort_order' => ['nullable', 'integer', 'min:1'],

            'pif' => ['nullable', 'array'],
            'pif.tour_leaders' => ['nullable', 'array'],
            'pif.tour_leaders.*.type' => ['nullable', 'string', 'max:255'],
            'pif.tour_leaders.*.name' => ['nullable', 'string', 'max:255'],
            'pif.tour_leaders.*.contact_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
