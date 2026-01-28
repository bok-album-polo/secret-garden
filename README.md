# Secret Garden

A two part web application:
- Collection portal: for the collection of information hidden behind a page knocking mechanism that can be deployed across multiple domains.
- Admin portal: for the management of collected information

## 🌿 Core Philosophy

- **Minimalism**: No external frameworks. Clean, readable code that is easy to audit and maintain.
- **Stealth**: Authentication is hidden behind behavioral sequences (Page Knocking) rather than standard login gates. The goal is for the portal to be so unremarkable that it is dismissed by human and automated observers alike.
- **Layered Reality**: The system acts as a dynamic reality bridge. For the public, it is a simple gardening site; for authorized users, it is a secure registration system unlocked only by intent and behavior.
- **Digital Resiliency**: By using native PHP and PostgreSQL with zero external upstream dependencies, the portal is immune to supply chain attacks and ensures long-term stability in scrutinized environments.
- **Privacy as a Service**: The system ensures the very existence of a user base or registration list remains non-obvious to external observers.

### Why Stealth?

Traditional login pages (`/admin`, `/login`) are primary targets for automated brute-force attacks and manual discovery. By using **Page Knocking**, the authentication interface simply does not exist for the average visitor or discovery bot.

This architectural choice creates a protective "Safe Harbor." It manifests only when a specific, predefined interaction pattern is detected, allowing for secure coordination while maintaining a public persona as a simple, static portal.

---

## 🛡️ Security & Stealth Mechanisms

```mermaid
flowchart TD
    L1[GET request]
    L1 --> D1{!pk_auth && !pk_banned && !ip_banned}
    D1 --> |false|DP1{page==$secret_door && pk_auth}
    A5 --> DP1
        A6 --> DP1
        A3 --> |false|DP1
    subgraph Authentication Sequence
        A2(Add page to $pk_history)
        A2 --> A3{number of elements in $pk_history > $pk_length}
        A3 --> |true|A4(Extract $pk_length elements of $pk_history into $pk_sequence)
        A4 --> A4B(validate $pk_sequence against db)
        A4B --> |is_valid|A5($pk_auth=true)
        A4B --> |!is_valid|A6(if count $pk_history > $pk_max; $pk_banned = true)
    end
    D1 --> |true|A2

subgraph Render Page
    DP1 --> |true|DP2[Display Secret Page]
    DP1 --> |false|DP3[Display standard content]
end
```

```mermaid
flowchart TD
    L1[POST request]
    L1 --> D1{page=$secret_door && pk_auth}
    D1 --> |true|A1[Record Secret Form submission]
    D1 --> |false|DP1[Record submission content]
    DP1 --> DP2[Add IP to `ip_bans` table]
```

---

## 🛠️ Technical Stack

- **Web Server**: Nginx with PHP-FPM.
- **Database**: PostgreSQL with custom roles and Row-Level Security.
- **Backend**: Clean PHP (No Frameworks).
- **Frontend**: Minimal HTML. No CSS frameworks, no responsive design.

---

## ⚙️ Configuration

### PHP Constants (`source/config/config.php`)

- `ENVIRONMENT`: `development` (debug tools on) or `production` (stealth mode).
- `DATABASE_HOST`: PostgreSQL container hostname.
- `DATABASE_NAME`: Target database name.
- `DATABASE_USER / PASSWORD`: App-logic user credentials.
- `DATABASE_USER_MASTER_NAME / PASSWORD`: Database owner credentials.

### PHP Constants (`source/config/config.php`)

Application specific configuration:
- `PK_LENGTH`: Length of correct knocking sequence (Default: `5`).
- `PK_MAX_HISTORY`: Max visits before session lockout (Default: `10`).
- `GENERATED_PASSWORD_LENGTH`: Length of the password generated for new users (Default: `10`).
- `SECRET_DOOR_ID`: The ID of the page where the Secret Door is located (Default: `2`).
- `SECRET_PAGE_ID`: The ID of the hidden registration template (Default: `7`).
- `PAGES`: Source of truth for all valid routes and metadata.

---

## 🚀 Getting Started

TODO
