<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupMember;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerGroupService
{
    /**
     * Create a customer group from a confirmed enquiry.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGroup(array $data): CustomerGroup
    {
        return DB::transaction(function () use ($data) {
            $enquiryId = $data['enquiry_id'] ?? null;

            if ($enquiryId) {
                $enquiry = Enquiry::findOrFail($enquiryId);
            }

            $group = CustomerGroup::create([
                'enquiry_id' => $enquiryId,
                'created_by' => auth()->id(),
            ]);

            // Process members (unified list with is_leader flag)
            foreach ($data['members'] as $member) {
                $customer = $this->findOrCreateCustomer($member);
                CustomerGroupMember::create([
                    'customer_group_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($member['is_leader'] ?? false),
                ]);
            }

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerGroup',
                    'subject_id' => $group->id,
                    'enquiry_id' => $enquiryId,
                ])
                ->log('Customer group created' . ($enquiryId ? ' for enquiry #' . $enquiryId : ''));

            return $group->load('members.customer.user');
        });
    }

    /**
     * Find an existing customer by email or create a new one.
     *
     * @param  array<string, mixed>  $customerData
     */
    private function findOrCreateCustomer(array $customerData): Customer
    {
        $email = $customerData['email'] ?? null;

        // Try to find existing user by email
        if ($email) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $existingUser->customer) {
                // Update customer fields if provided
                $this->updateCustomerIfNeeded($existingUser->customer, $customerData);

                return $existingUser->customer;
            }

            if ($existingUser) {
                // User exists but no customer record, create one
                $customer = Customer::create([
                    'user_id' => $existingUser->id,
                    'nric_number' => $customerData['nric_number'] ?? null,
                    'address' => $customerData['address'] ?? null,
                ]);

                return $customer;
            }
        }

        // Create new user + customer
        $user = User::create([
            'name' => $customerData['name'] ?? $customerData['name'] ?? '',
            'email' => $email,
            'contact' => $customerData['contact_number'] ?? null,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $user->id,
            'nric_number' => $customerData['nric_number'] ?? null,
            'address' => $customerData['address'] ?? null,
        ]);

        return $customer;
    }

    /**
     * Update customer fields if additional data is provided.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateCustomerIfNeeded(Customer $customer, array $data): void
    {
        $customerUpdates = [];
        if (! empty($data['nric_number'])) {
            $customerUpdates['nric_number'] = $data['nric_number'];
        }
        if (! empty($data['address'])) {
            $customerUpdates['address'] = $data['address'];
        }

        if (! empty($customerUpdates)) {
            $customer->update($customerUpdates);
        }

        // Update user fields if provided
        if ($customer->user) {
            $userUpdates = [];
            $name = $data['name'] ?? $data['name'] ?? null;
            if (! empty($name)) {
                $userUpdates['name'] = $name;
            }
            if (! empty($data['contact_number'])) {
                $userUpdates['contact'] = $data['contact_number'];
            }
            if (! empty($userUpdates)) {
                $customer->user->update($userUpdates);
            }
        }
    }

    /**
     * Search customers by email for autocomplete.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchCustomers(string $query): array
    {
        return User::query()
            ->whereHas('customer')
            ->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->with('customer')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->customer->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact,
                    'customer_number' => $user->customer->customer_number,
                    'nric_number' => $user->customer->nric_number,
                    'address' => $user->customer->address,
                ];
            })
            ->all();
    }

    /**
     * Get group details by enquiry ID.
     */
    public function getByEnquiryId(int $enquiryId): ?CustomerGroup
    {
        return CustomerGroup::with('members.customer.user')
            ->where('enquiry_id', $enquiryId)
            ->first();
    }

    /**
     * Get all customer groups for the grouped index page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForGroupedIndex(): array
    {
        return CustomerGroup::with(['members.customer.user', 'enquiry'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerGroup $group) {
                $leader = $group->members->firstWhere('is_leader', true);
                $participants = $group->members->where('is_leader', false)->values();

                return [
                    'id' => $group->id,
                    'enquiry_id' => $group->enquiry_id,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'leader_name' => $leader?->customer?->user?->name ?? '-',
                    'leader_email' => $leader?->customer?->user?->email ?? '-',
                    'leader_contact' => $leader?->customer?->user?->contact ?? '-',
                    'leader_customer_number' => $leader?->customer?->customer_number ?? '-',
                    'member_count' => $group->members->count(),
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    /**
     * List all active customers for selection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActiveCustomers(): array
    {
        return User::query()
            ->whereHas('customer')
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'label' => $user->email,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact ?? '',
                    'nric_number' => $user->customer->nric_number ?? '',
                    'address' => $user->customer->address ?? '',
                ];
            })
            ->all();
    }
}
