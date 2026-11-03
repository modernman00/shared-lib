<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Src\Realtime\SovereignBroadcaster;

class SovereignBroadcasterTest extends TestCase
{
    private PDO $pdo;
    private SovereignBroadcaster $broadcaster;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->broadcaster = new SovereignBroadcaster(
            $this->pdo,
            'test_app_key',
            'test_app_secret',
            300
        );
        $this->broadcaster->ensureSchema();
    }

    public function testTriggerDispatchesEventToChannels(): void
    {
        $count = $this->broadcaster->trigger(
            ['party-room-101', 'presence-party-room'],
            'PartyPulse.AuxSpin',
            ['track' => 'Afrobeats Live', 'user' => 'Wally']
        );

        $this->assertSame(2, $count);

        $events = $this->broadcaster->getEvents('party-room-101');
        $this->assertCount(1, $events);
        $this->assertSame('PartyPulse.AuxSpin', $events[0]['event']);
        $this->assertSame('Afrobeats Live', $events[0]['data']['track']);
        $this->assertSame('Wally', $events[0]['data']['user']);
    }

    public function testPrivateChannelAuthentication(): void
    {
        $auth = $this->broadcaster->authenticateChannel('private-user-88', 'socket_123.456');

        $this->assertArrayHasKey('auth', $auth);
        $this->assertStringStartsWith('test_app_key:', $auth['auth']);

        $isValid = $this->broadcaster->verifyChannelAuth('private-user-88', 'socket_123.456', $auth['auth']);
        $this->assertTrue($isValid);

        // Verification fails with tampered signature
        $isInvalid = $this->broadcaster->verifyChannelAuth('private-user-88', 'socket_123.456', 'test_app_key:bad_signature');
        $this->assertFalse($isInvalid);
    }

    public function testPresenceChannelAuthenticationAndMembers(): void
    {
        $userData = ['name' => 'Wally', 'role' => 'Host'];
        $auth = $this->broadcaster->authenticateChannel('presence-room-4', 'socket_789', $userData);

        $this->assertArrayHasKey('auth', $auth);
        $this->assertArrayHasKey('channel_data', $auth);

        $isValid = $this->broadcaster->verifyChannelAuth(
            'presence-room-4',
            'socket_789',
            $auth['auth'],
            $auth['channel_data']
        );
        $this->assertTrue($isValid);

        // Register member presence
        $this->broadcaster->registerPresenceMember('presence-room-4', 'user_wally', $userData, 'socket_789');

        $members = $this->broadcaster->getPresenceMembers('presence-room-4', 60);
        $this->assertArrayHasKey('user_wally', $members);
        $this->assertSame('Host', $members['user_wally']['role']);
    }

    public function testPruneStaleEvents(): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sovereign_broadcast_events (channel, event_name, payload, created_at)
            VALUES ('room-old', 'old.event', '{}', :created_at)
        ");
        $stmt->execute([':created_at' => time() - 600]);

        $pruned = $this->broadcaster->pruneStaleEvents();
        $this->assertGreaterThanOrEqual(1, $pruned);

        $events = $this->broadcaster->getEvents('room-old');
        $this->assertCount(0, $events);
    }

    public function testRejectsCrlfSseFrameInjectionInChannelAndEventNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->broadcaster->trigger(
            "public-room\r\nevent: hijacked\ndata: {\"admin\":true}",
            'legit.event',
            ['ok' => true]
        );
    }
}
