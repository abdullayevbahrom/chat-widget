<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TelegramWebhookIpTest extends TestCase
{
    /**
     * Telegram IP range'larini tekshirish uchun helper metod.
     * Bu TelegramWebhookController::isTelegramIp() logikasini takrorlaydi.
     */
    protected function isTelegramIp(string $ip): bool
    {
        $ranges = [
            '149.154.160.0/20',
            '91.108.4.0/22',
            '5.255.255.0/24',
        ];

        foreach ($ranges as $range) {
            [$subnet, $mask] = array_pad(explode('/', $range), 2, '32');
            $mask = (int) $mask;

            if ($mask === 32) {
                if ($ip === $subnet) {
                    return true;
                }

                continue;
            }

            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                continue;
            }

            $maskLong = -1 << (32 - $mask);

            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }

    #[DataProvider('telegramIpProvider')]
    public function test_is_telegram_ip_returns_true_for_telegram_ips(string $ip): void
    {
        $this->assertTrue(
            $this->isTelegramIp($ip),
            "Expected {$ip} to be recognized as a Telegram IP"
        );
    }

    #[DataProvider('nonTelegramIpProvider')]
    public function test_is_telegram_ip_returns_false_for_non_telegram_ips(string $ip): void
    {
        $this->assertFalse(
            $this->isTelegramIp($ip),
            "Expected {$ip} to NOT be recognized as a Telegram IP"
        );
    }

    public static function telegramIpProvider(): array
    {
        return [
            '149.154.160.0' => ['149.154.160.0'],
            '149.154.175.255' => ['149.154.175.255'],
            '149.154.167.100' => ['149.154.167.100'],
            '91.108.4.0' => ['91.108.4.0'],
            '91.108.7.255' => ['91.108.7.255'],
            '91.108.5.50' => ['91.108.5.50'],
            '5.255.255.0' => ['5.255.255.0'],
            '5.255.255.255' => ['5.255.255.255'],
            '5.255.255.100' => ['5.255.255.100'],
        ];
    }

    public static function nonTelegramIpProvider(): array
    {
        return [
            '8.8.8.8' => ['8.8.8.8'],
            '1.1.1.1' => ['1.1.1.1'],
            '192.168.1.1' => ['192.168.1.1'],
            '10.0.0.1' => ['10.0.0.1'],
            '172.16.0.1' => ['172.16.0.1'],
            '149.154.159.255' => ['149.154.159.255'], // Just before range
            '149.154.176.0' => ['149.154.176.0'],     // Just after range
            '91.108.3.255' => ['91.108.3.255'],       // Just before range
            '91.108.8.0' => ['91.108.8.0'],           // Just after range
        ];
    }
}
