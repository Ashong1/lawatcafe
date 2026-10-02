# Entity relationship diagram

> Generated from the live foreign keys on 2026-10-02. GitHub renders the diagram below. To put it in the paper, paste the code block into https://mermaid.live and export PNG/SVG.

## Point of sale and inventory

```mermaid
erDiagram
    users ||--o{ ingredient_deliveries : "reviewed_by"
    users ||--o{ ingredient_deliveries : "user_id"
    ingredient_deliveries ||--o{ ingredient_delivery_items : "ingredient_delivery_id"
    ingredients ||--o{ ingredient_delivery_items : "ingredient_id"
    purchase_order_drafts ||--o{ ingredient_delivery_items : "purchase_order_draft_id"
    ingredients ||--o{ inventory_logs : "ingredient_id"
    users ||--o{ inventory_logs : "user_id"
    ingredients ||--o{ product_ingredients : "ingredient_id"
    products ||--o{ product_ingredients : "product_id"
    users ||--o{ purchase_order_drafts : "created_by_user_id"
    ingredients ||--o{ purchase_order_drafts : "ingredient_id"
    suppliers ||--o{ purchase_order_drafts : "supplier_id"
    products ||--o{ sale_items : "product_id"
    sales ||--o{ sale_items : "sale_id"
    users ||--o{ sale_void_requests : "requested_by"
    users ||--o{ sale_void_requests : "reviewed_by"
    sales ||--o{ sale_void_requests : "sale_id"
    shifts ||--o{ sales : "shift_id"
    users ||--o{ sales : "user_id"
    shifts ||--o{ shift_transactions : "shift_id"
    users ||--o{ shift_transactions : "user_id"
    users ||--o{ shifts : "user_id"
    ingredients ||--o{ wastages : "ingredient_id"
    users ||--o{ wastages : "user_id"
    categories {
        bigint id PK
        varchar name
        varchar slug
        text description
        varchar icon
        tinyint is_food
        varchar color
        int sort_order
        timestamp created_at
        timestamp updated_at
    }
    ingredient_deliveries {
        bigint id PK
        varchar supplier_name
        timestamp delivery_date
        decimal total_cost
        varchar reference_number
        text note
        bigint user_id FK
        varchar status
        tinyint auto_confirmed
        bigint reviewed_by FK
        string more_columns "see data dictionary"
    }
    ingredient_delivery_items {
        bigint id PK
        bigint ingredient_delivery_id FK
        bigint ingredient_id FK
        bigint purchase_order_draft_id FK
        decimal quantity
        decimal cost_per_unit
        timestamp created_at
        timestamp updated_at
    }
    ingredients {
        bigint id PK
        varchar name
        decimal current_stock
        varchar unit
        varchar packaging_unit
        decimal capacity_per_pack
        decimal low_stock_threshold
        varchar status
        timestamp created_at
        timestamp updated_at
    }
    inventory_logs {
        bigint id PK
        bigint ingredient_id FK
        decimal change_amount
        decimal after_amount
        varchar reason
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }
    product_ingredients {
        bigint id PK
        bigint product_id FK
        bigint ingredient_id FK
        decimal quantity
        timestamp created_at
        timestamp updated_at
    }
    products {
        bigint id PK
        varchar name
        varchar category
        decimal price
        varchar status
        varchar image_path
        timestamp created_at
        timestamp updated_at
    }
    purchase_order_drafts {
        bigint id PK
        bigint ingredient_id FK
        bigint supplier_id FK
        decimal suggested_quantity
        decimal estimated_unit_cost
        decimal estimated_total_cost
        varchar status
        text notes
        varchar created_by_actor_type
        bigint created_by_user_id FK
        string more_columns "see data dictionary"
    }
    sale_items {
        bigint id PK
        bigint sale_id FK
        bigint product_id FK
        varchar category
        varchar item_name
        int quantity
        decimal price
        varchar type
        varchar kds_status
        varchar note
        string more_columns "see data dictionary"
    }
    sale_void_requests {
        bigint id PK
        bigint sale_id FK
        bigint requested_by FK
        text reason
        varchar status
        bigint reviewed_by FK
        timestamp reviewed_at
        timestamp created_at
        timestamp updated_at
    }
    sales {
        bigint id PK
        varchar transaction_number
        decimal total_amount
        decimal amount_received
        varchar status
        varchar payment_method
        varchar payment_reference
        varchar order_type
        varchar discount_type
        decimal discount_amount
        string more_columns "see data dictionary"
    }
    shift_transactions {
        bigint id PK
        bigint shift_id FK
        enum type
        decimal amount
        varchar reason
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }
    shifts {
        bigint id PK
        bigint user_id FK
        decimal starting_cash
        decimal expected_cash
        decimal ending_cash
        datetime opened_at
        datetime closed_at
        varchar status
        text notes
        timestamp created_at
        string more_columns "see data dictionary"
    }
    suppliers {
        bigint id PK
        varchar name
        varchar contact_person
        varchar phone
        varchar viber
        varchar email
        text delivery_days
        tinyint status
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        varchar name
        varchar username
        varchar email
        timestamp email_verified_at
        varchar password
        timestamp password_set_at
        timestamp deactivated_at
        varchar remember_token
        timestamp created_at
        string more_columns "see data dictionary"
    }
    wastages {
        bigint id PK
        bigint ingredient_id FK
        decimal quantity
        varchar reason
        text note
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }
```

## Network and captive portal

```mermaid
erDiagram
    sales ||--o{ vouchers : "sale_id"
    bandwidth_samples {
        bigint id PK
        timestamp sampled_at
        decimal down_mbps
        decimal up_mbps
        smallint active_guests
        decimal ceiling_mbps
        timestamp created_at
        timestamp updated_at
    }
    banned_devices {
        bigint id PK
        text mac_address
        varchar mac_address_hash
        varchar reason
        varchar hostname
        timestamp created_at
        timestamp updated_at
    }
    network_health_checks {
        bigint id PK
        timestamp checked_at
        varchar overall
        decimal internet_latency_ms
        decimal internet_loss_pct
        tinyint dns_ok
        smallint dhcp_used
        smallint dhcp_size
        smallint guests_online
        smallint infrastructure_down
        string more_columns "see data dictionary"
    }
    portal_events {
        bigint id PK
        varchar type
        varchar ip_address
        varchar voucher_code
        longtext meta
        timestamp created_at
    }
    sales {
        bigint id PK
        varchar transaction_number
        decimal total_amount
        decimal amount_received
        varchar status
        varchar payment_method
        varchar payment_reference
        varchar order_type
        varchar discount_type
        decimal discount_amount
        string more_columns "see data dictionary"
    }
    static_ip_assignments {
        bigint id PK
        text mac_address
        varchar mac_address_hash
        varchar ip_address
        varchar hostname
        varchar kea_subnet_uuid
        varchar kea_reservation_uuid
        timestamp created_at
        timestamp updated_at
    }
    vouchers {
        bigint id PK
        bigint sale_id FK
        varchar code
        int duration_minutes
        enum tier
        tinyint is_used
        timestamp used_at
        timestamp activated_at
        timestamp disconnected_at
        varchar ip_address
        string more_columns "see data dictionary"
    }
```

## AI agent

```mermaid
erDiagram
    users ||--o{ ai_action_audits : "actor_user_id"
    users ||--o{ ai_action_audits : "approved_by_user_id"
    users ||--o{ ai_conversations : "user_id"
    ai_conversations ||--o{ ai_feedback : "conversation_id"
    users ||--o{ ai_feedback : "user_id"
    ai_analysis_runs ||--o{ ai_findings : "run_id"
    users ||--o{ ai_lessons : "reviewed_by"
    ai_action_audits {
        bigint id PK
        varchar tool_name
        text input_params
        text result
        varchar actor_type
        bigint actor_user_id FK
        bigint approved_by_user_id FK
        varchar status
        timestamp created_at
        timestamp updated_at
    }
    ai_analysis_runs {
        bigint id PK
        text narrative
        int signal_count
        timestamp created_at
        timestamp updated_at
    }
    ai_conversations {
        bigint id PK
        bigint user_id FK
        varchar context
        varchar title
        longtext messages
        timestamp last_message_at
        timestamp mined_at
        timestamp created_at
        timestamp updated_at
    }
    ai_feedback {
        bigint id PK
        varchar audience
        bigint user_id FK
        bigint conversation_id FK
        varchar signal
        tinyint sentiment
        text user_message
        text assistant_reply
        text note
        timestamp distilled_at
        string more_columns "see data dictionary"
    }
    ai_findings {
        bigint id PK
        bigint run_id FK
        varchar type
        varchar severity
        varchar summary
        longtext data
        varchar audience
        timestamp created_at
        timestamp updated_at
    }
    ai_lessons {
        bigint id PK
        varchar audience
        varchar kind
        varchar title
        text body
        text trigger
        longtext evidence
        smallint evidence_count
        varchar status
        bigint reviewed_by FK
        string more_columns "see data dictionary"
    }
    users {
        bigint id PK
        varchar name
        varchar username
        varchar email
        timestamp email_verified_at
        varchar password
        timestamp password_set_at
        timestamp deactivated_at
        varchar remember_token
        timestamp created_at
        string more_columns "see data dictionary"
    }
```

Relationships not enforced by a foreign key (by design): `sales.payment_method` is text; `products.category` stores the category name; vouchers link to devices by MAC hash, not a key, because guests have no account.