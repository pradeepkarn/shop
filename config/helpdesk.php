<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ticket Limits
    |--------------------------------------------------------------------------
    |
    | Most non-closed tickets one requester may have at a time, and most new
    | tickets one requester may open in a rolling 24 hours.
    |
    */
    'max_open_tickets' => 5,

    'max_new_tickets_per_day' => 10,

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Per message. Files are stored on the private "local" disk and streamed
    | through an authorised route.
    |
    */
    'max_attachments' => 3,

    'attachment_max_kb' => 5120,

    'attachment_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],

    /*
    |--------------------------------------------------------------------------
    | Content Lengths
    |--------------------------------------------------------------------------
    */
    'subject_min' => 5,

    'subject_max' => 150,

    'body_min' => 10,

    'body_max' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Auto-close
    |--------------------------------------------------------------------------
    |
    | Resolved tickets with no activity for this many days are closed by
    | the helpdesk:close-resolved command.
    |
    */
    'auto_close_resolved_days' => 7,
];
