<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Tenant::setCurrent($this->tenant);
    }

    protected function tearDown(): void
    {
        Tenant::clearCurrent();
        parent::tearDown();
    }

    #[Test]
    public function guest_cannot_access_profile_page(): void
    {
        $this->get('/dashboard/tenant-profile')
            ->assertRedirect('/auth/login');
    }

    #[Test]
    public function user_can_view_profile_page(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/tenant-profile')
            ->assertOk()
            ->assertSee('Tenant Profile')
            ->assertSee('Company Information')
            ->assertSee('company_name')
            ->assertSee('tax_id');
    }

    #[Test]
    public function profile_page_shows_existing_tenant_data(): void
    {
        $this->tenant->update([
            'company_name' => 'ACME Corp',
            'tax_id' => 'TAX123456',
            'contact_email' => 'info@acme.com',
        ]);

        $this->actingAs($this->user, 'tenant_user')
            ->get('/dashboard/tenant-profile')
            ->assertOk()
            ->assertSee('ACME Corp')
            ->assertSee('TAX123456')
            ->assertSee('info@acme.com');
    }

    #[Test]
    public function user_can_update_profile(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'company_name' => 'Updated Company',
                'tax_id' => 'TAX999999',
                'company_address' => '123 Main Street',
                'company_city' => 'Tashkent',
                'company_country' => 'UZ',
                'contact_phone' => '+998901234567',
                'contact_email' => 'updated@company.com',
                'website' => 'https://updated.com',
                'primary_contact_name' => 'John Doe',
                'primary_contact_title' => 'CEO',
            ])
            ->assertRedirect('/dashboard/tenant-profile')
            ->assertSessionHas('success');

        $this->tenant->refresh();

        $this->assertEquals('Updated Company', $this->tenant->company_name);
        $this->assertEquals('TAX999999', $this->tenant->tax_id);
        $this->assertEquals('123 Main Street', $this->tenant->company_address);
        $this->assertEquals('Tashkent', $this->tenant->company_city);
        $this->assertEquals('UZ', $this->tenant->company_country);
        $this->assertEquals('+998901234567', $this->tenant->contact_phone);
        $this->assertEquals('updated@company.com', $this->tenant->contact_email);
        $this->assertEquals('https://updated.com', $this->tenant->website);
        $this->assertEquals('John Doe', $this->tenant->primary_contact_name);
        $this->assertEquals('CEO', $this->tenant->primary_contact_title);
    }

    #[Test]
    public function user_can_upload_logo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png');

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'company_name' => 'Test Company',
                'logo' => $file,
            ])
            ->assertRedirect('/dashboard/tenant-profile')
            ->assertSessionHas('success');

        $this->tenant->refresh();

        $this->assertNotNull($this->tenant->logo_path);
        Storage::disk('public')->assertExists($this->tenant->logo_path);
    }

    #[Test]
    public function old_logo_is_deleted_when_new_logo_is_uploaded(): void
    {
        Storage::fake('public');

        // Upload first logo
        $firstLogo = UploadedFile::fake()->image('old-logo.png');
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'company_name' => 'Test Company',
                'logo' => $firstLogo,
            ]);

        $this->tenant->refresh();
        $oldLogoPath = $this->tenant->logo_path;
        Storage::disk('public')->assertExists($oldLogoPath);

        // Upload second logo
        $secondLogo = UploadedFile::fake()->image('new-logo.png');
        $this->put('/dashboard/tenant-profile', [
            'company_name' => 'Test Company',
            'logo' => $secondLogo,
        ]);

        $this->tenant->refresh();
        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertExists($this->tenant->logo_path);
    }

    #[Test]
    public function logo_must_be_valid_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'logo' => $file,
            ])
            ->assertSessionHasErrors('logo');
    }

    #[Test]
    public function logo_must_not_exceed_2mb(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('large-logo.png')->size(3000);

        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'logo' => $file,
            ])
            ->assertSessionHasErrors('logo');
    }

    #[Test]
    public function website_must_be_valid_url(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'website' => 'not-a-url',
            ])
            ->assertSessionHasErrors('website');
    }

    #[Test]
    public function contact_email_must_be_valid_email(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'contact_email' => 'invalid-email',
            ])
            ->assertSessionHasErrors('contact_email');
    }

    #[Test]
    public function country_must_be_two_characters(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'company_country' => 'USA',
            ])
            ->assertSessionHasErrors('company_country');
    }

    #[Test]
    public function user_can_update_partial_profile(): void
    {
        $this->actingAs($this->user, 'tenant_user')
            ->put('/dashboard/tenant-profile', [
                'company_name' => 'Only Company Name',
            ])
            ->assertRedirect('/dashboard/tenant-profile')
            ->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertEquals('Only Company Name', $this->tenant->company_name);
    }
}
