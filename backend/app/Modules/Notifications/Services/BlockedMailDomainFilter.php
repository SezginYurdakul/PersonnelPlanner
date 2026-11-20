<?php

namespace App\Modules\Notifications\Services;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxListHeader;

/**
 * Drops recipients on config('mail.blocked_domains') - the made-up addresses of the demo
 * accounts - before any email (invitation, schedule notice) is handed to the mail service,
 * so they never bounce. A message left with no recipient at all is not sent.
 */
class BlockedMailDomainFilter
{
    /**
     * @return ?false false cancels the message; null lets it (and other listeners) through
     */
    public function handle(MessageSending $event): ?bool
    {
        $blockedDomains = config('mail.blocked_domains', []);

        if ($blockedDomains === []) {
            return null;
        }

        $headers = $event->message->getHeaders();
        $skipped = [];

        foreach (['To', 'Cc', 'Bcc'] as $field) {
            $header = $headers->get($field);

            if (! $header instanceof MailboxListHeader) {
                continue;
            }

            $kept = [];
            foreach ($header->getAddresses() as $address) {
                if ($this->isBlocked($address, $blockedDomains)) {
                    $skipped[] = $address->getAddress();
                } else {
                    $kept[] = $address;
                }
            }

            if ($kept === []) {
                $headers->remove($field);
            } else {
                $header->setAddresses($kept);
            }
        }

        if ($skipped !== []) {
            Log::info('Skipped email to blocked demo domains.', ['recipients' => $skipped]);
        }

        if ($event->message->getTo() === [] && $event->message->getCc() === [] && $event->message->getBcc() === []) {
            return false;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $blockedDomains
     */
    private function isBlocked(Address $address, array $blockedDomains): bool
    {
        $domain = strtolower(substr(strrchr($address->getAddress(), '@') ?: '', 1));

        return in_array($domain, $blockedDomains, true);
    }
}
