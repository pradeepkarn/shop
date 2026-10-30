# Support (helpdesk) spec

In-house ticketing for customers (phase 1) and sellers (phase 2), worked by admin staff from one inbox.
Outside tools (live chat, WhatsApp) may feed tickets later (phase 3); the ticket tables stay the source of truth.

Module: `App\Helpdesk` (`App\Support` is already the helpers namespace).

## Phases

- **Phase 1 (merged)**: customer tickets, admin inbox, emails, auto-close.
- **Phase 2 (branch `support-phase-2`)**: seller tickets on the same tables (`requester_type = seller`), seller panel pages, seller categories, customer/seller filter in the inbox.
- **Phase 3 (optional)**: canned replies, SLA timers and reports, outside channels feeding tickets.

## Data

`support_tickets` (ULID id)
- `ticket_number` unique, e.g. `SUP-261030-4K7Q`; shown to requesters and used in URLs.
- `requester_type` (`customer` or `seller`), `user_id` (null on account deletion), `requester_name` (customer name or store name), `requester_email` snapshots.
- `seller_profile_id` for seller tickets (null on profile deletion).
- `category`, `subject`, `status`, `priority`.
- `order_id` / `order_number`: optional, must belong to the requester. Seller tickets also store `seller_order_id` (the seller's package). Looked up through `App\Helpdesk\Contracts\CustomerOrderLookupInterface` and `SellerOrderLookupInterface`, implemented in `App\Orders\Adapters` (Helpdesk never touches Orders models).
- `assigned_admin_id` (null on admin delete).
- `last_message_at`, `first_response_at`, `resolved_at`, `closed_at`.

`support_ticket_messages`
- `author_type`: `requester`, `staff`, `system`; `user_id` / `admin_id`.
- `body`, `is_internal` (staff notes and system notes requesters never see), `attachments` (paths on the private `local` disk).

## Statuses

| Status | Meaning | Who moves it here |
|---|---|---|
| `open` | Waiting on staff | New ticket; any requester reply (reopens pending/resolved) |
| `pending` | Waiting on the requester | Staff public reply (default) |
| `resolved` | Staff thinks it is done; requester can still reply to reopen | Staff |
| `closed` | Final, no more replies | Requester closes; staff closes; auto-close of resolved after 7 days |

Internal notes never change status. Closed tickets cannot be replied to; the requester opens a new one.

## Categories

- Customer: Order issue, Delivery & tracking, Payment & refund, Return & replacement, Product question, Account & login, Other.
- Seller: Orders & shipping, Returns/RTO & claims, Payouts & settlements, Fees & commission, Listings & catalog, KYC/bank & store account, Other.

A ticket's category must be one of its requester type's categories.

## Priority

Low, Normal (default), High, Urgent. Staff only.

## Rules

- At most 5 non-closed tickets and 10 new tickets per day, per requester type (a seller's store tickets do not count against their shopping account).
- Up to 3 attachments per message: jpg, jpeg, png, webp, pdf; 5 MB each.
- Subject 5–150 chars; first message 10–5000 chars; replies up to 5000 chars.
- First public staff reply sets `first_response_at` and, if the ticket is unassigned, assigns it to that staff member.
- Customer pages only show customer tickets and seller pages only seller tickets, even for a user who is both.

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

## Seller pages (`layouts.seller`)

Open to approved and suspended sellers (same middleware as orders and payments; suspended sellers often need support most).

- `/seller/support`, `/seller/support/new` (`?order=SELLER_ORDER_ID` preselects a package), `/seller/support/{ticket_number}`: same behaviour as the customer pages.
- Links: seller sidebar "Support", seller order page "Need help with this order?".

## Admin pages

- `/admin/support`: inbox. Filters: status (default "needs reply" = open), assignee (all / mine / unassigned), customers / sellers, category, priority, search (ticket number, subject, requester or store name, email, order number). Counts per status. Seller tickets carry a "Seller" badge.
- `/admin/support/{ticket_number}`: thread with internal notes, reply box (public reply or internal note, status after reply), side panel with requester (customer link, or seller link and store status), order link, status, priority, assignee.
- Sidebar "Support" with a badge for open unassigned tickets.

## Emails (queued, to the requester)

- Ticket received (acknowledgement with ticket number).
- New staff reply (no reply body in email, link to the ticket).
- Ticket resolved.

Links go to the storefront account for customers and the seller panel for sellers.

## Scheduled

`helpdesk:close-resolved` daily: resolved tickets with no activity for 7 days become closed.
