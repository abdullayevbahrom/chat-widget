<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * Service for tenant-isolated file storage operations.
 *
 * All file paths are automatically prefixed with the tenant ID
 * to prevent cross-tenant file access.
 */
class TenantFileService
{
    /**
     * The disk to use for storage.
     */
    protected string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.default');
    }

    /**
     * Get the tenant prefix for file paths.
     *
     * @throws LogicException if no tenant context is set
     */
    public function getTenantPrefix(): string
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            throw new LogicException('Cannot determine tenant file prefix: no tenant context is set.');
        }

        return "tenants/{$tenant->id}";
    }

    /**
     * Store a file with tenant prefix.
     *
     * @return string The stored file path (relative to disk root)
     */
    public function store(UploadedFile $file, string $subPath = ''): string
    {
        $tenantPrefix = $this->getTenantPrefix();
        $path = $subPath !== ''
            ? "{$tenantPrefix}/{$subPath}"
            : "{$tenantPrefix}/attachments";

        $fileName = $this->sanitizeFileName($file->getClientOriginalName());
        $uniqueName = Str::random(40).'_'.$fileName;
        $fullPath = "{$path}/{$uniqueName}";

        Storage::disk($this->disk)->put($fullPath, $file->getContent(), [
            'visibility' => 'private',
            'mime_type' => $file->getMimeType(),
        ]);

        return $fullPath;
    }

    /**
     * Get the full tenant-prefixed path for a relative path.
     */
    public function getPath(string $relativePath): string
    {
        $tenantPrefix = $this->getTenantPrefix();

        // If the path already starts with the tenant prefix, return as-is
        if (str_starts_with($relativePath, $tenantPrefix)) {
            return $relativePath;
        }

        return "{$tenantPrefix}/{$relativePath}";
    }

    /**
     * Get the URL for a file (if the disk supports URLs).
     */
    public function url(string $relativePath): string
    {
        $fullPath = $this->getPath($relativePath);

        return Storage::disk($this->disk)->url($fullPath);
    }

    /**
     * Delete a file, validating tenant ownership.
     *
     * @throws LogicException if trying to delete a file outside tenant scope
     */
    public function delete(string $fullPath): bool
    {
        $tenantPrefix = $this->getTenantPrefix();

        // Validate that the file path belongs to the current tenant
        if (! str_starts_with($fullPath, $tenantPrefix)) {
            throw new LogicException(
                "Cannot delete file outside current tenant scope: {$fullPath}"
            );
        }

        return Storage::disk($this->disk)->delete($fullPath);
    }

    /**
     * Check if a file exists within the current tenant scope.
     */
    public function exists(string $relativePath): bool
    {
        $fullPath = $this->getPath($relativePath);

        return Storage::disk($this->disk)->exists($fullPath);
    }

    /**
     * Get the contents of a file.
     *
     * @return string|false
     */
    public function get(string $relativePath): mixed
    {
        $fullPath = $this->getPath($relativePath);

        return Storage::disk($this->disk)->get($fullPath);
    }

    /**
     * Sanitize a file name to prevent path traversal and injection attacks.
     */
    protected function sanitizeFileName(string $fileName): string
    {
        // Remove any directory traversal attempts
        $fileName = basename($fileName);

        // Replace non-alphanumeric characters (except dots, dashes, underscores)
        $fileName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $fileName);

        // Limit length
        if (strlen($fileName) > 200) {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileName = substr($fileName, 0, 200 - strlen($extension) - 1).'.'.$extension;
        }

        return $fileName;
    }
}
