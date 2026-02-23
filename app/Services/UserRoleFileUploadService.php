<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserRoleFileUploadService
{
    public function processUploads(
        Model $model,
        array $data,
        array $fileKeyMap,
        string $baseDirectory,
        string $entityName,
    ): void {
        $updates = [];

        foreach ($fileKeyMap as $fileKey => $pathField) {
            $path = $this->storeUploadedFile(
                file: $data[$fileKey] ?? null,
                pathField: $pathField,
                baseDirectory: $baseDirectory,
                entityName: $entityName,
            );

            if ($path) {
                if ($model->{$pathField}) {
                    Storage::disk('public')->delete($model->{$pathField});
                }

                $updates[$pathField] = $path;

                continue;
            }

            $isPathCleared = array_key_exists($pathField, $data)
                && ($data[$pathField] === null || $data[$pathField] === '');

            $isFileCleared = array_key_exists($fileKey, $data)
                && ($data[$fileKey] === null || $data[$fileKey] === '');

            if (($isPathCleared || $isFileCleared) && $model->{$pathField}) {
                Storage::disk('public')->delete($model->{$pathField});
                $updates[$pathField] = null;
            }
        }

        if (! empty($updates)) {
            $model->update($updates);
        }
    }

    private function storeUploadedFile(
        mixed $file,
        string $pathField,
        string $baseDirectory,
        string $entityName,
    ): ?string {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $directory = "{$baseDirectory}/{$pathField}";
        $slugName = Str::slug($entityName ?: 'record');
        $fieldName = str_replace('_path', '', $pathField);
        $timestamp = now()->format('Ymd-His');
        $extension = $file->getClientOriginalExtension();
        $filename = "{$slugName}-{$fieldName}-{$timestamp}.{$extension}";

        return $file->storeAs($directory, $filename, 'public');
    }
}
