<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'session_id' => fake()->uuid(),
            'ip_address_encrypted' => fake()->optional()->text(255),
            'user_agent' => fake()->userAgent(),
            'referer' => fake()->optional()->url(),
            'current_url' => fake()->optional()->url(),
            'current_page' => fake()->optional()->text(500),
            'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'browser_version' => fake()->numberBetween(1, 120).'.0',
            'platform' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'platform_version' => fake()->randomElement(['10', '11', '12', '13', '14']),
            'language' => fake()->randomElement(['en-US', 'uz-UZ', 'ru-RU', 'de-DE', 'fr-FR']),
            'country' => fake()->optional()->country(),
            'city' => fake()->optional()->city(),
            'is_authenticated' => false,
            'user_id' => null,
            'first_visit_at' => fake()->dateTimeBetween('-30 days', '-7 days'),
            'last_visit_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'visit_count' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Indicate that the visitor is authenticated (linked to a user).
     */
    public function authenticated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_authenticated' => true,
            'user_id' => User::factory(),
        ]);
    }

    /**
     * Populate browser, platform, and device info with realistic data.
     */
    public function withBrowserInfo(): static
    {
        return $this->state(function (array $attributes) {
            $platforms = [
                'Windows' => ['browser' => 'Edge', 'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'],
                'macOS' => ['browser' => 'Safari', 'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'],
                'Linux' => ['browser' => 'Firefox', 'userAgent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0)'],
                'iOS' => ['browser' => 'Safari', 'device_type' => 'mobile', 'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)'],
                'Android' => ['browser' => 'Chrome', 'device_type' => 'mobile', 'userAgent' => 'Mozilla/5.0 (Linux; Android 13)'],
            ];

            $platform = fake()->randomElement(array_keys($platforms));
            $info = $platforms[$platform];

            return [
                'user_agent' => $info['userAgent'],
                'browser' => $info['browser'],
                'browser_version' => fake()->numberBetween(90, 125).'.0',
                'platform' => $platform,
                'platform_version' => fake()->randomElement(['10', '11', '12', '13', '14', '15', '16']),
                'device_type' => $info['device_type'] ?? fake()->randomElement(['desktop', 'tablet']),
            ];
        });
    }
}
