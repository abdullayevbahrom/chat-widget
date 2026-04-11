<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class TenantFileIsolationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function it_stores_files_with_tenant_prefix(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        $file = UploadedFile::fake()->create('test.txt', 100);
        $service = new TenantFileService('local');

        $path = $service->store($file, 'documents');

        $this->assertStringContainsString("tenants/{$tenant->id}", $path);
        $this->assertStringContainsString('documents', $path);

        Storage::disk('local')->assertExists($path);

        $this->clearTenantContext();
    }

    /** @test */
    public function it_prevents_deleting_files_outside_tenant_scope(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        $file = UploadedFile::fake()->create('test.txt', 100);
        $service = new TenantFileService('local');

        $path = $service->store($file, 'documents');

        // Try to delete a file outside tenant scope
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot delete file outside current tenant scope');

        $service->delete("tenants/99999/other/file.txt");

        $this->clearTenantContext();
    }

    /** @test */
    public function it_throws_exception_without_tenant_context(): void
    {
        Storage::fake('local');

        $this->clearTenantContext();

        $service = new TenantFileService('local');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot determine tenant file prefix');

        $service->getTenantPrefix();
    }

    /** @test */
    public function file_paths_are_isolated_between_tenants(): void
    {
        Storage::fake('local');

        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Store file as tenant A
        $this->actAsTenant($tenantA);
        $serviceA = new TenantFileService('local');
        $fileA = UploadedFile::fake()->create('file-a.txt', 100);
        $pathA = $serviceA->store($fileA, 'docs');

        $this->clearTenantContext();

        // Store file as tenant B
        $this->actAsTenant($tenantB);
        $serviceB = new TenantFileService('local');
        $fileB = UploadedFile::fake()->create('file-b.txt', 100);
        $pathB = $serviceB->store($fileB, 'docs');

        $this->clearTenantContext();

        // Verify paths are different
        $this->assertNotEquals($pathA, $pathB);
        $this->assertStringContainsString("tenants/{$tenantA->id}", $pathA);
        $this->assertStringContainsString("tenants/{$tenantB->id}", $pathB);
    }
}
