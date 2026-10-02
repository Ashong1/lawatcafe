# Data dictionary

> Generated from the live database schema (MariaDB 10.11, `lawat_db`) on 2026-10-02. Regenerate rather than edit by hand when migrations change. Laravel framework tables (cache, cache_locks, failed_jobs, job_batches, jobs, migrations, password_reset_tokens, sessions) are left out.

Key: **PRI** primary key, **UNI** unique, **MUL** indexed (usually a foreign key).

## Tables at a glance

| Group | Table | Purpose |
|---|---|---|
| Accounts | [`users`](#users) | Everyone who signs in: staff (barista), admin (owner) and super_admin (system administrator). Invited accounts have no password_set_at until the person chooses a password; removed staff with history get deactivated_at instead of being deleted. |
| POS | [`categories`](#categories) | Menu groups with icon, colour, order and an is_food flag used by the pairing suggestion. |
| POS | [`products`](#products) | Items sold at the register, with price, category, status and optional photo. |
| POS | [`sale_items`](#sale_items) | Line items of a sale, including Wi-Fi code lines; kds_status tracks each item in the kitchen. |
| POS | [`sale_void_requests`](#sale_void_requests) | Staff requests to void a sale, waiting for an admin to approve or reject. |
| POS | [`sales`](#sales) | One row per order: totals, payment method (Cash, GCash, Maya, QR Ph), e-wallet reference, dine-in/take-away, kitchen status, cashier and shift. |
| POS | [`shift_transactions`](#shift_transactions) | Cash in / cash out recorded during a shift. |
| POS | [`shifts`](#shifts) | A cashier shift: starting cash, expected and counted cash at close. |
| Inventory | [`ingredient_deliveries`](#ingredient_deliveries) | Deliveries received, optionally matched to a sent purchase order. |
| Inventory | [`ingredient_delivery_items`](#ingredient_delivery_items) | Line items of a delivery. |
| Inventory | [`ingredients`](#ingredients) | Raw materials with stock in a base unit, optional packaging unit and a low-stock threshold. |
| Inventory | [`inventory_logs`](#inventory_logs) | Every stock change with its reason (sale, restock, wastage, adjustment). |
| Inventory | [`product_ingredients`](#product_ingredients) | Recipe: how much of each ingredient one unit of a product uses (pivot). |
| Inventory | [`purchase_order_drafts`](#purchase_order_drafts) | Purchase orders drafted (often by Barista AI on low stock) and sent to suppliers. |
| Inventory | [`suppliers`](#suppliers) | Where ingredients are bought. |
| Inventory | [`wastages`](#wastages) | Spoiled or spilled stock, deducted from inventory. |
| Network | [`bandwidth_samples`](#bandwidth_samples) | Periodic samples of link throughput used by the adaptive fair-use ceiling. |
| Network | [`banned_devices`](#banned_devices) | Devices blocked from the Wi-Fi (MAC encrypted at rest). |
| Network | [`network_health_checks`](#network_health_checks) | Result of each minute-by-minute check of internet, firewall, DNS filter, portal and shop equipment (kept one week). |
| Network | [`portal_events`](#portal_events) | Captive-portal events per device (page opened, code redeemed, connected, dropped) for the Sign-in Report. |
| Network | [`static_ip_assignments`](#static_ip_assignments) | Fixed IP reservations mirrored from OPNsense Kea DHCP (MAC encrypted at rest). |
| Network | [`vouchers`](#vouchers) | Wi-Fi access codes: duration, tier, when redeemed/activated/disconnected, bound device (MAC encrypted at rest, with a hash column for lookups). |
| AI agent | [`ai_action_audits`](#ai_action_audits) | Every tool the AI used or proposed: inputs, result, status (proposed, executed, rejected, failed) and who approved it. |
| AI agent | [`ai_analysis_runs`](#ai_analysis_runs) | Each scheduled cross-domain analysis pass (sales + Wi-Fi + inventory). |
| AI agent | [`ai_conversations`](#ai_conversations) | Saved Barista AI chat conversations per user. |
| AI agent | [`ai_feedback`](#ai_feedback) | Thumbs up/down and corrections on AI replies, input to the learning loop. |
| AI agent | [`ai_findings`](#ai_findings) | Findings produced by analysis runs (e.g. Wi-Fi codes used without matching sales). |
| AI agent | [`ai_lessons`](#ai_lessons) | Lessons distilled from feedback; only approved lessons are given to the AI. |
| System | [`notifications`](#notifications) | In-app notifications shown under the bell (Laravel database notifications). |
| System | [`settings`](#settings) | Key/value store settings: store hours, Wi-Fi prices, reminder time, receipt switch, e-wallet QR pictures, AI options. |

## Accounts

### users

Everyone who signs in: staff (barista), admin (owner) and super_admin (system administrator). Invited accounts have no password_set_at until the person chooses a password; removed staff with history get deactivated_at instead of being deleted.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `name` | varchar(255) | NO |  |  |  |
| `username` | varchar(30) | YES | UNI |  |  |
| `email` | varchar(255) | NO | UNI |  |  |
| `email_verified_at` | timestamp | YES |  |  |  |
| `password` | varchar(255) | NO |  |  |  |
| `password_set_at` | timestamp | YES |  |  |  |
| `deactivated_at` | timestamp | YES |  |  |  |
| `remember_token` | varchar(100) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |
| `role` | varchar(255) | NO |  | staff |  |

## POS

### categories

Menu groups with icon, colour, order and an is_food flag used by the pairing suggestion.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `name` | varchar(255) | NO |  |  |  |
| `slug` | varchar(255) | YES | UNI |  |  |
| `description` | text | YES |  |  |  |
| `icon` | varchar(255) | YES |  | coffee |  |
| `is_food` | tinyint(1) | NO |  | 0 |  |
| `color` | varchar(255) | YES |  | #3E2723 |  |
| `sort_order` | int(11) | NO |  | 0 |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### products

Items sold at the register, with price, category, status and optional photo.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `name` | varchar(255) | NO |  |  |  |
| `category` | varchar(255) | NO |  | Coffee |  |
| `price` | decimal(10,2) | NO |  |  |  |
| `status` | varchar(255) | NO |  | Active |  |
| `image_path` | varchar(255) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### sale_items

Line items of a sale, including Wi-Fi code lines; kds_status tracks each item in the kitchen.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `sale_id` | bigint(20) unsigned | NO | MUL |  | → sales.id |
| `product_id` | bigint(20) unsigned | YES | MUL |  | → products.id |
| `category` | varchar(255) | YES |  |  |  |
| `item_name` | varchar(255) | YES | MUL |  |  |
| `quantity` | int(11) | NO |  |  |  |
| `price` | decimal(10,2) | NO |  |  |  |
| `type` | varchar(255) | NO |  | product |  |
| `kds_status` | varchar(255) | NO |  | pending |  |
| `note` | varchar(255) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### sale_void_requests

Staff requests to void a sale, waiting for an admin to approve or reject.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `sale_id` | bigint(20) unsigned | NO | MUL |  | → sales.id |
| `requested_by` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `reason` | text | NO |  |  |  |
| `status` | varchar(255) | NO |  | pending |  |
| `reviewed_by` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `reviewed_at` | timestamp | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### sales

One row per order: totals, payment method (Cash, GCash, Maya, QR Ph), e-wallet reference, dine-in/take-away, kitchen status, cashier and shift.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `transaction_number` | varchar(255) | NO | UNI |  |  |
| `total_amount` | decimal(10,2) | NO |  |  |  |
| `amount_received` | decimal(10,2) | YES |  |  |  |
| `status` | varchar(255) | NO | MUL | pending |  |
| `payment_method` | varchar(255) | NO |  | Cash |  |
| `payment_reference` | varchar(40) | YES |  |  |  |
| `order_type` | varchar(255) | NO |  | dine_in |  |
| `discount_type` | varchar(255) | YES |  |  |  |
| `discount_amount` | decimal(10,2) | NO |  | 0.00 |  |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `shift_id` | bigint(20) unsigned | YES | MUL |  | → shifts.id |
| `created_at` | timestamp | YES | MUL |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### shift_transactions

Cash in / cash out recorded during a shift.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `shift_id` | bigint(20) unsigned | NO | MUL |  | → shifts.id |
| `type` | enum('pay_in','pay_out') | NO |  |  |  |
| `amount` | decimal(15,2) | NO |  |  |  |
| `reason` | varchar(255) | NO |  |  |  |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### shifts

A cashier shift: starting cash, expected and counted cash at close.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `starting_cash` | decimal(10,2) | NO |  |  |  |
| `expected_cash` | decimal(10,2) | NO |  | 0.00 |  |
| `ending_cash` | decimal(10,2) | YES |  |  |  |
| `opened_at` | datetime | NO |  |  |  |
| `closed_at` | datetime | YES |  |  |  |
| `status` | varchar(255) | NO |  | open |  |
| `notes` | text | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

## Inventory

### ingredient_deliveries

Deliveries received, optionally matched to a sent purchase order.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `supplier_name` | varchar(255) | NO |  |  |  |
| `delivery_date` | timestamp | NO |  |  |  |
| `total_cost` | decimal(10,2) | NO |  | 0.00 |  |
| `reference_number` | varchar(255) | YES |  |  |  |
| `note` | text | YES |  |  |  |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `status` | varchar(255) | NO |  | confirmed |  |
| `auto_confirmed` | tinyint(1) | NO |  | 0 |  |
| `reviewed_by` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `reviewed_at` | timestamp | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ingredient_delivery_items

Line items of a delivery.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `ingredient_delivery_id` | bigint(20) unsigned | NO | MUL |  | → ingredient_deliveries.id |
| `ingredient_id` | bigint(20) unsigned | NO | MUL |  | → ingredients.id |
| `purchase_order_draft_id` | bigint(20) unsigned | YES | MUL |  | → purchase_order_drafts.id |
| `quantity` | decimal(15,2) | NO |  |  |  |
| `cost_per_unit` | decimal(10,2) | NO |  | 0.00 |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ingredients

Raw materials with stock in a base unit, optional packaging unit and a low-stock threshold.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `name` | varchar(255) | NO |  |  |  |
| `current_stock` | decimal(20,2) | NO |  |  |  |
| `unit` | varchar(255) | NO |  |  |  |
| `packaging_unit` | varchar(255) | YES |  |  |  |
| `capacity_per_pack` | decimal(15,2) | NO |  | 1.00 |  |
| `low_stock_threshold` | decimal(10,2) | NO |  | 500.00 |  |
| `status` | varchar(255) | NO |  | In Stock |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### inventory_logs

Every stock change with its reason (sale, restock, wastage, adjustment).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `ingredient_id` | bigint(20) unsigned | NO | MUL |  | → ingredients.id |
| `change_amount` | decimal(20,2) | NO |  |  |  |
| `after_amount` | decimal(20,2) | NO |  |  |  |
| `reason` | varchar(255) | NO |  |  |  |
| `user_id` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### product_ingredients

Recipe: how much of each ingredient one unit of a product uses (pivot).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `product_id` | bigint(20) unsigned | NO | MUL |  | → products.id |
| `ingredient_id` | bigint(20) unsigned | NO | MUL |  | → ingredients.id |
| `quantity` | decimal(10,2) | NO |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### purchase_order_drafts

Purchase orders drafted (often by Barista AI on low stock) and sent to suppliers.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `ingredient_id` | bigint(20) unsigned | NO | MUL |  | → ingredients.id |
| `supplier_id` | bigint(20) unsigned | YES | MUL |  | → suppliers.id |
| `suggested_quantity` | decimal(15,2) | NO |  |  |  |
| `estimated_unit_cost` | decimal(10,2) | YES |  |  |  |
| `estimated_total_cost` | decimal(10,2) | YES |  |  |  |
| `status` | varchar(255) | NO |  | draft |  |
| `notes` | text | YES |  |  |  |
| `created_by_actor_type` | varchar(255) | NO |  | human |  |
| `created_by_user_id` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### suppliers

Where ingredients are bought.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `name` | varchar(255) | NO |  |  |  |
| `contact_person` | varchar(255) | YES |  |  |  |
| `phone` | varchar(255) | YES |  |  |  |
| `viber` | varchar(255) | YES |  |  |  |
| `email` | varchar(255) | YES |  |  |  |
| `delivery_days` | text | YES |  |  |  |
| `status` | tinyint(1) | NO |  | 1 |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### wastages

Spoiled or spilled stock, deducted from inventory.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `ingredient_id` | bigint(20) unsigned | NO | MUL |  | → ingredients.id |
| `quantity` | decimal(15,2) | NO |  |  |  |
| `reason` | varchar(255) | NO |  |  |  |
| `note` | text | YES |  |  |  |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

## Network

### bandwidth_samples

Periodic samples of link throughput used by the adaptive fair-use ceiling.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `sampled_at` | timestamp | NO | MUL |  |  |
| `down_mbps` | decimal(8,2) | NO |  |  |  |
| `up_mbps` | decimal(8,2) | NO |  |  |  |
| `active_guests` | smallint(5) unsigned | NO |  | 0 |  |
| `ceiling_mbps` | decimal(8,2) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### banned_devices

Devices blocked from the Wi-Fi (MAC encrypted at rest).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `mac_address` | text | YES |  |  |  |
| `mac_address_hash` | varchar(64) | YES | UNI |  | HMAC blind index for exact-match lookups on the encrypted column |
| `reason` | varchar(255) | YES |  |  |  |
| `hostname` | varchar(255) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### network_health_checks

Result of each minute-by-minute check of internet, firewall, DNS filter, portal and shop equipment (kept one week).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `checked_at` | timestamp | NO | MUL |  |  |
| `overall` | varchar(8) | NO |  |  |  |
| `internet_latency_ms` | decimal(8,1) | YES |  |  |  |
| `internet_loss_pct` | decimal(5,1) | YES |  |  |  |
| `dns_ok` | tinyint(1) | YES |  |  |  |
| `dhcp_used` | smallint(5) unsigned | YES |  |  |  |
| `dhcp_size` | smallint(5) unsigned | YES |  |  |  |
| `guests_online` | smallint(5) unsigned | YES |  |  |  |
| `infrastructure_down` | smallint(5) unsigned | NO |  | 0 |  |
| `results` | longtext | NO |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### portal_events

Captive-portal events per device (page opened, code redeemed, connected, dropped) for the Sign-in Report.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `type` | varchar(32) | NO | MUL |  |  |
| `ip_address` | varchar(45) | YES | MUL |  |  |
| `voucher_code` | varchar(32) | YES | MUL |  |  |
| `meta` | longtext | YES |  |  |  |
| `created_at` | timestamp | NO | MUL | current_timestamp() | Laravel timestamp |

### static_ip_assignments

Fixed IP reservations mirrored from OPNsense Kea DHCP (MAC encrypted at rest).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `mac_address` | text | YES |  |  |  |
| `mac_address_hash` | varchar(64) | YES | UNI |  | HMAC blind index for exact-match lookups on the encrypted column |
| `ip_address` | varchar(255) | NO | UNI |  |  |
| `hostname` | varchar(255) | YES |  |  |  |
| `kea_subnet_uuid` | varchar(255) | YES |  |  |  |
| `kea_reservation_uuid` | varchar(255) | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### vouchers

Wi-Fi access codes: duration, tier, when redeemed/activated/disconnected, bound device (MAC encrypted at rest, with a hash column for lookups).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `sale_id` | bigint(20) unsigned | YES | MUL |  | → sales.id |
| `code` | varchar(255) | NO | UNI |  |  |
| `duration_minutes` | int(11) | NO |  |  |  |
| `tier` | enum('free','premium') | NO |  | free |  |
| `is_used` | tinyint(1) | NO | MUL | 0 |  |
| `used_at` | timestamp | YES |  |  |  |
| `activated_at` | timestamp | YES |  |  |  |
| `disconnected_at` | timestamp | YES |  |  |  |
| `ip_address` | varchar(45) | YES |  |  |  |
| `mac_address` | text | YES |  |  |  |
| `mac_address_hash` | varchar(64) | YES | MUL |  | HMAC blind index for exact-match lookups on the encrypted column |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

## AI agent

### ai_action_audits

Every tool the AI used or proposed: inputs, result, status (proposed, executed, rejected, failed) and who approved it.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `tool_name` | varchar(255) | NO | MUL |  |  |
| `input_params` | text | YES |  |  |  |
| `result` | text | YES |  |  |  |
| `actor_type` | varchar(255) | NO |  |  |  |
| `actor_user_id` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `approved_by_user_id` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `status` | varchar(255) | NO |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ai_analysis_runs

Each scheduled cross-domain analysis pass (sales + Wi-Fi + inventory).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `narrative` | text | NO |  |  |  |
| `signal_count` | int(10) unsigned | NO |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ai_conversations

Saved Barista AI chat conversations per user.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `user_id` | bigint(20) unsigned | NO | MUL |  | → users.id |
| `context` | varchar(255) | NO |  |  |  |
| `title` | varchar(255) | YES |  |  |  |
| `messages` | longtext | NO |  | '[]' |  |
| `last_message_at` | timestamp | YES |  |  |  |
| `mined_at` | timestamp | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ai_feedback

Thumbs up/down and corrections on AI replies, input to the learning loop.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `audience` | varchar(16) | NO | MUL |  |  |
| `user_id` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `conversation_id` | bigint(20) unsigned | YES | MUL |  | → ai_conversations.id |
| `signal` | varchar(24) | NO | MUL |  |  |
| `sentiment` | tinyint(4) | NO |  | 0 |  |
| `user_message` | text | YES |  |  |  |
| `assistant_reply` | text | YES |  |  |  |
| `note` | text | YES |  |  |  |
| `distilled_at` | timestamp | YES | MUL |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ai_findings

Findings produced by analysis runs (e.g. Wi-Fi codes used without matching sales).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `run_id` | bigint(20) unsigned | NO | MUL |  | → ai_analysis_runs.id |
| `type` | varchar(255) | NO |  |  |  |
| `severity` | varchar(255) | NO |  |  |  |
| `summary` | varchar(255) | NO |  |  |  |
| `data` | longtext | YES |  |  |  |
| `audience` | varchar(255) | NO | MUL |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### ai_lessons

Lessons distilled from feedback; only approved lessons are given to the AI.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `audience` | varchar(16) | NO | MUL |  |  |
| `kind` | varchar(16) | NO | MUL | lesson |  |
| `title` | varchar(255) | NO |  |  |  |
| `body` | text | NO |  |  |  |
| `trigger` | text | YES |  |  |  |
| `evidence` | longtext | YES |  |  |  |
| `evidence_count` | smallint(5) unsigned | NO |  | 0 |  |
| `status` | varchar(16) | NO | MUL | proposed |  |
| `reviewed_by` | bigint(20) unsigned | YES | MUL |  | → users.id |
| `reviewed_at` | timestamp | YES |  |  |  |
| `review_note` | text | YES |  |  |  |
| `times_applied` | int(10) unsigned | NO |  | 0 |  |
| `fingerprint` | varchar(64) | NO | UNI |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

## System

### notifications

In-app notifications shown under the bell (Laravel database notifications).

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | char(36) | NO | PRI |  | Primary key |
| `type` | varchar(255) | NO |  |  |  |
| `notifiable_type` | varchar(255) | NO | MUL |  |  |
| `notifiable_id` | bigint(20) unsigned | NO |  |  |  |
| `data` | text | NO |  |  |  |
| `read_at` | timestamp | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |

### settings

Key/value store settings: store hours, Wi-Fi prices, reminder time, receipt switch, e-wallet QR pictures, AI options.

| Column | Type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  | Primary key |
| `key` | varchar(255) | NO | UNI |  |  |
| `value` | text | YES |  |  |  |
| `created_at` | timestamp | YES |  |  | Laravel timestamp |
| `updated_at` | timestamp | YES |  |  | Laravel timestamp |
