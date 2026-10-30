<?php

namespace App\Helpdesk\Enums;

enum TicketStatus: string
{
    /** Waiting on staff. */
    case Open = 'open';

    /** Staff replied; waiting on the requester (customer or seller). */
    case Pending = 'pending';

    /** Staff thinks it is done; a requester reply reopens it until it auto-closes. */
    case Resolved = 'resolved';

    /** Final; no more replies. */
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Awaiting requester',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Wording for the requester, who sees the ticket from the other side.
     */
    public function requesterLabel(): string
    {
        return match ($this) {
            self::Open => 'Waiting for support',
            self::Pending => 'Waiting for your reply',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
            self::Pending => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20',
            self::Resolved => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
            self::Closed => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-600/30',
        };
    }

    public function acceptsReplies(): bool
    {
        return $this !== self::Closed;
    }
}
