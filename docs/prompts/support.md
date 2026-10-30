# Support (helpdesk) spec

In-house ticketing for customers (phase 1) and sellers (phase 2), worked by admin staff from one inbox.
Outside tools (live chat, WhatsApp) may feed tickets later (phase 3); the ticket tables stay the source of truth.

Module: `App\Helpdesk` (`App\Support` is already the helpers namespace).

## Phases

- **Phase 1 (this branch, `support-phase-1`)**: customer tickets, admin inbox, emails, auto-close.
- **Phase 2**: seller tickets on the same tables (`requester_type = seller`), seller panel pages, seller categories (payout, listing, KYC, order fulfilment).
- **Phase 3 (optional)**: canned replies, SLA timers and reports, outside channels feeding tickets.

## Data

`support_tickets` (ULID id)
- `ticket_number` unique, e.g. `SUP-261030-4K7Q`; shown to customers and used in URLs.
- `requester_type` (`customer`; `seller` in phase 2), `user_id` (null on account deletion), `requester_name`, `requester_email` snapshots.
- `category`, `subject`, `status`, `priority`.
- `order_id` / `order_number`: optional, must belong to the requester. Looked up through `App\Helpdesk\Contracts\CustomerOrderLookupInterface`, implemented in `App\Orders\Adapters` (Helpdesk never touches Orders models).
- `assigned_admin_id` (null on admin delete).
- `last_message_at`, `first_response_at`, `resolved_at`, `closed_at`.

`support_ticket_messages`
- `author_type`: `requester`, `staff`, `system`; `user_id` / `admin_id`.
- `body`, `is_internal` (staff notes and system notes customers never see), `attachments` (paths on the private `local` disk).

## Statuses

| Status | Meaning | Who moves it here |
|---|---|---|
| `open` | Waiting on staff | New ticket; any customer reply (reopens pending/resolved) |
| `pending` | Waiting on the customer | Staff public reply (default) |
| `resolved` | Staff thinks it is done; customer can still reply to reopen | Staff |
| `closed` | Final, no more replies | Customer closes; staff closes; auto-close of resolved after 7 days |

Internal notes never change status. Closed tickets cannot be replied to; the customer opens a new one.

## Categories (customer)

Order issue, Delivery & tracking, Payment & refund, Return & replacement, Product question, Account & login, Other.

## Priority

Low, Normal (default), High, Urgent. Staff only.

## Rules

- At most 5 non-closed tickets per customer and 10 new tickets per customer per day.
- Up to 3 attachments per message: jpg, jpeg, png, webp, pdf; 5 MB each.
- Subject 5–150 chars; first message 10–5000 chars; replies up to 5000 chars.
- First public staff reply sets `first_response_at` and, if the ticket is unassigned, assigns it to that staff member.

## Permissions

- `support.view`: see the inbox and tickets.
- `support.reply`: reply, add internal notes, change status and priority, take an unassigned ticket.
- `support.assign`: assign any ticket to any active staff member.

All admin changes go to the activity log.

## Customer pages (`layouts.app`)

- `/support`: my tickets, newest activity first, "waiting on you" highlighted.
- `/support/new`: category, optional order (recent orders; `?order=ORDER_NUMBER` preselects), subject, message, attachments.
- `/support/{ticket_number}`: thread (no internal notes), reply with attachments, close.
- Links: customer sidebar "Help & Support", order page "Need help with this order?".

## Admin pages

- `/admin/support`: inbox. Filters: status (default "needs reply" = open), assignee (all / mine / unassigned), category, priority, search (ticket number, subject, requester name or email, order number). Counts per status.
- `/admin/support/{ticket_number}`: thread with internal notes, reply box (public reply or internal note, status after reply), side panel with requester, order link, status, priority, assignee.
- Sidebar "Support" with a badge for open unassigned tickets.

## Emails (queued, to the requester)

- Ticket received (acknowledgement with ticket number).
- New staff reply (no reply body in email, link to the ticket).
- Ticket resolved.

## Scheduled

`helpdesk:close-resolved` daily: resolved tickets with no activity for 7 days become closed.
