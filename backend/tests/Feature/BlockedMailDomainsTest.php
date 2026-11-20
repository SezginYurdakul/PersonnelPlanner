<?php

namespace Tests\Feature;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

/**
 * Demo accounts have made-up addresses; mail to their domains is never handed to the mail
 * service (BlockedMailDomainFilter), while real addresses are mailed as usual.
 */
class BlockedMailDomainsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array', 'mail.blocked_domains' => ['bakeryx.test', 'bakareyx.com']]);
    }

    public function test_mail_only_to_blocked_domains_is_not_sent(): void
    {
        Mail::raw('Your schedule changed.', fn ($message) => $message->to('menno@bakeryx.test')->cc('admin@BAKAREYX.com'));

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_blocked_recipients_are_dropped_from_mail_to_real_addresses(): void
    {
        Mail::raw('Your schedule changed.', fn ($message) => $message->to(['menno@bakeryx.test', 'planner@example.org'])->bcc('admin@bakareyx.com'));

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame(['planner@example.org'], $this->addresses($sent->first()->getOriginalMessage()->getTo()));
        $this->assertSame([], $sent->first()->getOriginalMessage()->getBcc());
    }

    public function test_nothing_is_blocked_without_blocked_domains(): void
    {
        config(['mail.blocked_domains' => []]);

        Mail::raw('Your schedule changed.', fn ($message) => $message->to('menno@bakeryx.test'));

        $this->assertCount(1, $this->sentMessages());
    }

    /**
     * @return Collection<int, SentMessage>
     */
    private function sentMessages(): Collection
    {
        return collect(Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->getAddress(), $addresses);
    }
}
