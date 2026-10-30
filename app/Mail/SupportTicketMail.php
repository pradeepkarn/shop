<?php

namespace App\Mail;

use App\Helpdesk\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Support ticket updates for the requester.
 * Events: received, staff_reply, resolved.
 */
class SupportTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const SUBJECTS = [
        'received' => 'We have your request',
        'staff_reply' => 'New reply on your request',
        'resolved' => 'Your request is resolved',
    ];

    public function __construct(
        public SupportTicket $ticket,
        public string $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::SUBJECTS[$this->event] ?? 'Support update').' ['.$this->ticket->ticket_number.'] - '.brand_name());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support-ticket',
            with: [
                'event' => $this->event,
                'name' => $this->ticket->requester_name,
                'ticketNumber' => $this->ticket->ticket_number,
                'subjectLine' => $this->ticket->subject,
                'url' => $this->ticket->requesterUrl(),
                'autoCloseDays' => (int) config('helpdesk.auto_close_resolved_days'),
            ],
        );
    }
}
