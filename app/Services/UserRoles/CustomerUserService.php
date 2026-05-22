<?php

namespace App\Services\UserRoles;

use App\Models\Customer;
use App\Models\ModelFile;
use App\Models\User;
use App\Rules\UserRule;
use App\Services\NotificationService;
use App\Services\NumberingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CustomerUserService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected NumberingService $numberingService,
    ) {}

    public function getForDataTable()
    {
        return User::query()
            ->whereDoesntHave('ghostUser')
            ->role('customer')
            ->with('roles', 'customer.files')
            ->get()
            ->map(function ($user) {
                $user->role = 'customer';
                $user->contact = $user->contact ?? '';

                if ($user->customer) {
                    $user->nric_number = $user->customer->nric_number ?? '';
                    $user->address = $user->customer->address ?? '';
                    $user->nationality = $user->customer->nationality ?? '';
                    $user->passport_number = $user->customer->passport_number ?? '';
                    $user->passport_issue_date = $user->customer->passport_issue_date_formatted ?? '';
                    $user->passport_expiry_date = $user->customer->passport_expiry_date_formatted ?? '';
                    $user->passport_place_of_issue = $user->customer->passport_place_of_issue ?? '';
                    $user->gender = $user->customer->gender ?? '';
                    $user->marital_status = $user->customer->marital_status ?? '';
                    $user->date_of_birth = $user->customer->date_of_birth_formatted ?? '';
                    $user->place_of_birth = $user->customer->place_of_birth ?? '';
                    $user->first_time_umrah = $user->customer->first_time_umrah ?? false;
                    $user->has_chronic_disease = $user->customer->has_chronic_disease ?? false;
                    $user->is_using_wheelchair = $user->customer->is_using_wheelchair ?? false;
                    $user->chronic_disease_details = $user->customer->chronic_disease_details ?? '';
                    $user->passport_document = $user->customer->files?->firstWhere('field', 'passport') ? $this->formatDocumentPayload($user->customer->files->firstWhere('field', 'passport')) : null;
                    $user->photo_document = $user->customer->files?->firstWhere('field', 'photo') ? $this->formatDocumentPayload($user->customer->files->firstWhere('field', 'photo')) : null;
                    $user->passport_documents = $this->formatDocumentListPayload($user->customer->files?->where('field', 'passport') ?? collect());
                    $user->photo_documents = $this->formatDocumentListPayload($user->customer->files?->where('field', 'photo') ?? collect());
                }

                return $user;
            });
    }

    public function importFromPayload(array $items): array
    {
        $imported = 0;
        $errors = [];
        $userRule = new UserRule;

        foreach ($items as $index => $item) {
            $row = $index + 1;

            try {
                $item['role'] = 'customer';
                $item['password_confirmation'] = $item['password'] ?? null;

                $validator = Validator::make($item, $userRule->rules('customer'));

                if ($validator->fails()) {
                    $messages = collect($validator->errors()->all())->implode(' | ');
                    $errors[] = ['row' => $row, 'message' => "Validation: {$messages}"];

                    continue;
                }

                $this->store($validator->validated());
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row, 'message' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createOrRestoreUser($data);

            $user->syncRoles([Role::findByName('customer')]);

            $existingCustomer = Customer::query()
                ->where('user_id', $user->id)
                ->first();

            $customer = Customer::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'customer_number' => $this->numberingService->ensureNumber(
                    'customer',
                    $data['customer_number'] ?? null,
                    $existingCustomer?->id,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'nric_number' => $data['nric_number'] ?? null,
                'address' => $data['address'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'passport_number' => $data['passport_number'] ?? null,
                'passport_issue_date' => $data['passport_issue_date'] ?? null,
                'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                'passport_place_of_issue' => $data['passport_place_of_issue'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'first_time_umrah' => $data['first_time_umrah'] ?? null,
                'has_chronic_disease' => $data['has_chronic_disease'] ?? false,
                'is_using_wheelchair' => $data['is_using_wheelchair'] ?? false,
                'chronic_disease_details' => $data['chronic_disease_details'] ?? null,
                'last_login' => null,
            ]);

            if (array_key_exists('passport_documents', $data)) {
                $this->persistCustomerDocuments($customer, 'passport', $data['passport_documents'] ?? [], $user->name);
            }

            if (array_key_exists('photo_documents', $data)) {
                $this->persistCustomerDocuments($customer, 'photo', $data['photo_documents'] ?? [], $user->name);
            }

            $this->notificationService->createNotification([
                'title' => 'New Customer Created',
                'message' => "{$user->name} has just created as a customer. Do you want to handle it?",
                'type' => 'info',
                'link' => '/customer',
                'exclusive' => false,
            ], [], ['admin', 'sales'], null);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'CustomerUser', 'subject_id' => $user->id ?? null])
                ->log('CustomerUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    private function createOrRestoreUser(array $data): User
    {
        $hashedPassword = isset($data['password']) && $data['password']
            ? Hash::make((string) $data['password'])
            : Hash::make('password');

        $existingUser = User::withTrashed()
            ->where('email', (string) $data['email'])
            ->first();

        if ($existingUser && $existingUser->trashed()) {
            $existingUser->restore();
            $existingUser->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => $hashedPassword,
            ]);

            return $existingUser->fresh();
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact' => $data['contact'] ?? null,
            'password' => $hashedPassword,
        ]);
    }

    public function getForEditShow($id)
    {
        $user = User::role('customer')->with('customer.files')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'customer',
            'company_name' => '',
            'customer_id' => $user->customer->id ?? null,
            'customer_number' => $user->customer->customer_number ?? '',
            'nric_number' => $user->customer->nric_number ?? '',
            'address' => $user->customer->address ?? '',
            'nationality' => $user->customer->nationality ?? '',
            'passport_number' => $user->customer->passport_number ?? '',
            'passport_issue_date' => $user->customer->passport_issue_date_formatted ?? '',
            'passport_expiry_date' => $user->customer->passport_expiry_date_formatted ?? '',
            'passport_place_of_issue' => $user->customer->passport_place_of_issue ?? '',
            'gender' => $user->customer->gender ?? '',
            'marital_status' => $user->customer->marital_status ?? '',
            'date_of_birth' => $user->customer->date_of_birth_formatted ?? '',
            'place_of_birth' => $user->customer->place_of_birth ?? '',
            'first_time_umrah' => $user->customer->first_time_umrah ?? false,
            'has_chronic_disease' => $user->customer->has_chronic_disease ?? false,
            'is_using_wheelchair' => $user->customer->is_using_wheelchair ?? false,
            'chronic_disease_details' => $user->customer->chronic_disease_details ?? '',
            'passport_document' => $user->customer->files?->firstWhere('field', 'passport') ? $this->formatDocumentPayload($user->customer->files->firstWhere('field', 'passport')) : null,
            'photo_document' => $user->customer->files?->firstWhere('field', 'photo') ? $this->formatDocumentPayload($user->customer->files->firstWhere('field', 'photo')) : null,
            'passport_documents' => $this->formatDocumentListPayload($user->customer->files?->where('field', 'passport') ?? collect()),
            'photo_documents' => $this->formatDocumentListPayload($user->customer->files?->where('field', 'photo') ?? collect()),
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::role('customer')->with('customer.files')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            if ($user->customer) {
                $user->customer->update([
                    'customer_number' => array_key_exists('customer_number', $data)
                        ? $this->numberingService->ensureNumber(
                            'customer',
                            $data['customer_number'],
                            (int) $user->customer->id,
                            isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                        )
                        : $user->customer->customer_number,
                    'nric_number' => $data['nric_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'nationality' => $data['nationality'] ?? null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_issue_date' => $data['passport_issue_date'] ?? null,
                    'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                    'passport_place_of_issue' => $data['passport_place_of_issue'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'marital_status' => $data['marital_status'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'first_time_umrah' => $data['first_time_umrah'] ?? null,
                    'has_chronic_disease' => $data['has_chronic_disease'] ?? false,
                    'is_using_wheelchair' => $data['is_using_wheelchair'] ?? false,
                    'chronic_disease_details' => $data['chronic_disease_details'] ?? null,
                ]);

                if (array_key_exists('passport_documents', $data)) {
                    $this->persistCustomerDocuments($user->customer, 'passport', $data['passport_documents'] ?? [], $user->name);
                }

                if (array_key_exists('photo_documents', $data)) {
                    $this->persistCustomerDocuments($user->customer, 'photo', $data['photo_documents'] ?? [], $user->name);
                }
            }

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'CustomerUser', 'subject_id' => $user->id ?? null])
                ->log('CustomerUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function persistCustomerDocuments(Customer $customer, string $field, array $documents, string $customerName): void
    {
        $rowsToPersist = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            if ((bool) ($document['removed'] ?? false)) {
                continue;
            }

            $uploadedPath = $this->storeUploadedFile($document['file'] ?? null, $field);
            $requestedName = $this->normalizeNullableString($document['file_name'] ?? null);
            $defaultFileName = $this->buildDefaultDocumentName($field, $customerName, $document['file'] ?? null);
            $existingPath = $this->normalizeNullableString($document['file_path'] ?? null);
            $filePath = $uploadedPath ?? $existingPath;

            if (! $filePath) {
                continue;
            }

            $rowsToPersist[] = [
                'field' => $field,
                'file_name' => $requestedName ?? $defaultFileName ?? $field,
                'file_path' => $filePath,
            ];
        }

        $existingFiles = $customer->files()->where('field', $field)->get();
        $preservedPaths = collect($rowsToPersist)
            ->pluck('file_path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->all();

        foreach ($existingFiles as $existingFile) {
            if (! in_array($existingFile->file_path, $preservedPaths, true) && $existingFile->file_path) {
                $this->deleteStoredFileIfUnreferenced(
                    $existingFile->file_path,
                    (int) $existingFile->id,
                );
            }
        }

        $customer->files()->where('field', $field)->delete();

        foreach ($rowsToPersist as $row) {
            $customer->files()->create($row);
        }
    }

    private function storeUploadedFile(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store("customers/{$field}", 'public');
    }

    private function buildDefaultDocumentName(string $field, string $customerName, mixed $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $safeCustomerName = trim($customerName) !== '' ? trim($customerName) : 'Customer';

        return ucfirst($field).' '.$safeCustomerName.($extension !== '' ? '.'.$extension : '');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ModelFile>  $rows
     * @return array<int, array{id:int,field:string,file_name:string,file_path:string}>
     */
    private function formatDocumentListPayload($rows): array
    {
        return $rows
            ->map(function (ModelFile $row): array {
                return [
                    'id' => $row->id,
                    'field' => $row->field,
                    'file_name' => $row->file_name,
                    'file_path' => $row->file_path,
                ];
            })
            ->values()
            ->all();
    }

    private function formatDocumentPayload(?ModelFile $modelFile): ?array
    {
        if (! $modelFile) {
            return null;
        }

        return [
            'field' => $modelFile->field,
            'file_name' => $modelFile->file_name,
            'file_path' => $modelFile->file_path,
        ];
    }

    private function deleteStoredFileIfUnreferenced(string $filePath, int $excludedModelFileId): void
    {
        $normalizedPath = trim($filePath);

        if ($normalizedPath === '') {
            return;
        }

        $isReferencedElsewhere = ModelFile::query()
            ->where('file_path', $normalizedPath)
            ->where('id', '!=', $excludedModelFileId)
            ->exists();

        if (! $isReferencedElsewhere) {
            Storage::disk('public')->delete($normalizedPath);
        }
    }
}
