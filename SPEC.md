# DH Google Reviews Widget — Plugin Specification

**Version:** 1.0
**Author:** Digital Hitmen
**Status:** Ready for development
**Target:** WordPress 6.4+ / PHP 8.1+ / WooCommerce compatible (not dependent)

---

## 1. Purpose and Scope

A lightweight WordPress plugin that displays Google Business Profile reviews on client websites using the Google Business Profile (GBP) API. Designed for agency deployment across the Digital Hitmen portfolio (~31 sites on Cloudways shared infrastructure).

### Primary Goals

- Pull live reviews from GBP without using the Google Places API
- Cache reviews locally to minimise API calls and eliminate frontend latency
- Output valid `AggregateRating` and `Review` schema markup automatically
- Provide a manual fallback (CPT) for clients without GBP API access
- Render via shortcode and Gutenberg block with configurable display options
- Zero cost per request (GBP API is free for account owners/managers)

### Out of Scope (v1)

- Review reply functionality from within WordPress
- Multi-location aggregation into a single widget (each shortcode instance maps to one location)
- Review solicitation or review gating features
- Third-party review platform integration (Trustpilot, Facebook, etc.)

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                  WordPress Site                      │
│                                                      │
│  ┌──────────┐   ┌──────────────┐   ┌──────────────┐ │
│  │ Gutenberg │   │  Shortcode   │   │  PHP Widget  │ │
│  │  Block    │   │  [dh_reviews]│   │  (Sidebar)   │ │
│  └────┬─────┘   └──────┬───────┘   └──────┬───────┘ │
│       │                │                   │         │
│       └────────────────┼───────────────────┘         │
│                        ▼                             │
│              ┌─────────────────┐                     │
│              │  Render Engine  │                     │
│              │  (Template +    │                     │
│              │   Schema Output)│                     │
│              └────────┬────────┘                     │
│                       ▼                              │
│              ┌─────────────────┐                     │
│              │  Review Store   │                     │
│              │  (CPT: dh_review│                     │
│              │   + meta)       │                     │
│              └────────┬────────┘                     │
│                       ▲                              │
│          ┌────────────┴────────────┐                 │
│          ▼                         ▼                 │
│  ┌───────────────┐      ┌──────────────────┐        │
│  │  GBP API Sync │      │  Manual Entry /  │        │
│  │  (WP Cron)    │      │  CSV Import      │        │
│  └───────┬───────┘      └──────────────────┘        │
│          │                                           │
└──────────┼───────────────────────────────────────────┘
           ▼
   ┌───────────────┐
   │ Google BPP API│
   │ (OAuth 2.0)   │
   └───────────────┘
```

---

## 3. Google Business Profile API Integration

### 3.1 Authentication

**Method:** OAuth 2.0 with refresh token (offline access)

**Setup Flow:**

1. Agency creates a Google Cloud project with the "My Business Account Management API" and "My Business Business Information API" enabled
2. Create OAuth 2.0 credentials (Web application type)
3. In the plugin settings, the admin initiates the OAuth consent flow
4. Plugin stores the refresh token in `wp_options` (encrypted using `wp_salt('auth')`)
5. Access tokens are refreshed automatically when expired (tokens last 60 minutes)

**Required Scopes:**

- `https://www.googleapis.com/auth/business.manage` (covers review reading)

**Token Storage:**

| Option Key | Value |
|---|---|
| `dh_reviews_access_token` | Encrypted access token string |
| `dh_reviews_refresh_token` | Encrypted refresh token string |
| `dh_reviews_token_expiry` | Unix timestamp |
| `dh_reviews_gcp_client_id` | Client ID from GCP console |
| `dh_reviews_gcp_client_secret` | Encrypted client secret |

### 3.2 API Endpoints Used

**List Accounts**
```
GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts
```
Used during setup to let the admin select which account/location to pull reviews from.

**List Locations**
```
GET https://mybusinessbusinessinformation.googleapis.com/v1/{account_name}/locations
```
Returns all locations under the selected account. Admin selects the target location.

**List Reviews**
```
GET https://mybusiness.googleapis.com/v4/{location_name}/reviews
```
Returns paginated reviews. Plugin fetches all pages on sync and stores locally.

**Response Fields to Store:**

| API Field | Local Storage |
|---|---|
| `reviewer.displayName` | `_dh_reviewer_name` meta |
| `reviewer.profilePhotoUrl` | `_dh_reviewer_photo` meta |
| `starRating` | `_dh_star_rating` meta (enum: ONE through FIVE, stored as int 1 to 5) |
| `comment` | `post_content` of the CPT |
| `createTime` | `post_date` of the CPT |
| `updateTime` | `_dh_review_updated` meta |
| `reviewReply.comment` | `_dh_owner_reply` meta |
| `reviewReply.updateTime` | `_dh_reply_date` meta |
| `reviewId` | `_dh_gbp_review_id` meta (used for deduplication) |

### 3.3 Sync Mechanism

**Trigger:** WP Cron event `dh_reviews_sync` running every 12 hours (configurable: 6h / 12h / 24h).

**Sync Logic:**

1. Refresh access token if expired
2. Call `reviews.list` with `pageSize=50`, paginate through all results
3. For each review, check if `_dh_gbp_review_id` already exists in the CPT
4. If new: create CPT post with status `publish` (or `draft` if below minimum star threshold)
5. If existing: update meta fields if `updateTime` has changed
6. If a review exists locally but is absent from the API response: set post status to `trash` (review was deleted on Google)
7. Update the aggregate rating transient (see Section 5)
8. Log sync result to `dh_reviews_sync_log` option (last 10 syncs, timestamp + count + errors)

**Rate Limiting:** The GBP API has a default quota of 60 QPM. A single sync for one location with <200 reviews will use 4 to 5 requests. No throttling needed for typical agency use.

**Manual Sync:** An "Sync Now" button in the admin settings triggers an immediate sync outside the cron schedule.

---

## 4. Data Model — Custom Post Type

### 4.1 CPT Registration

**Post Type:** `dh_review`
**Public:** false (not directly accessible via URL)
**Show in Admin:** true (under a "Reviews" top level menu)
**Supports:** `title`, `editor` (title = reviewer name, editor = review text)
**Has Archive:** false
**Exclude from Search:** true
**Menu Icon:** `dashicons-star-filled`

### 4.2 Meta Fields

| Meta Key | Type | Source |
|---|---|---|
| `_dh_star_rating` | int (1 to 5) | API `starRating` or manual entry |
| `_dh_reviewer_name` | string | API `reviewer.displayName` or manual |
| `_dh_reviewer_photo` | URL string | API `reviewer.profilePhotoUrl` |
| `_dh_review_source` | enum: `gbp_api`, `manual`, `csv_import` | Set on creation |
| `_dh_gbp_review_id` | string | API `reviewId` (unique, indexed) |
| `_dh_review_updated` | datetime | API `updateTime` |
| `_dh_owner_reply` | text | API `reviewReply.comment` |
| `_dh_reply_date` | datetime | API `reviewReply.updateTime` |
| `_dh_review_location` | string | GBP location resource name |
| `_dh_review_verified` | bool | Default true for API sourced, false for manual |

### 4.3 Taxonomy

**Taxonomy:** `dh_review_location` (hierarchical: false)
Purpose: Tag reviews by location for multi-location sites (e.g., Kitchen Warehouse with 18 stores). Shortcode and block filter by this taxonomy.

---

## 5. Schema Markup Output

### 5.1 AggregateRating

Calculated and stored as a transient (`dh_reviews_aggregate_{location_slug}`) on every sync or manual review save. Transient expiry matches sync interval.

**Calculation:**
- `ratingValue`: Mean of all published `_dh_star_rating` values, rounded to 1 decimal
- `reviewCount`: Count of all published `dh_review` posts for that location
- `bestRating`: 5
- `worstRating`: 1

**Output Format (JSON-LD):**

```json
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "{{business_name}}",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "{{street}}",
    "addressLocality": "{{city}}",
    "addressRegion": "{{state}}",
    "postalCode": "{{postcode}}",
    "addressCountry": "AU"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{{calculated_mean}}",
    "reviewCount": "{{count}}",
    "bestRating": "5",
    "worstRating": "1"
  },
  "review": [
    {
      "@type": "Review",
      "author": {
        "@type": "Person",
        "name": "{{reviewer_name}}"
      },
      "datePublished": "{{review_date}}",
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": "{{star_rating}}",
        "bestRating": "5",
        "worstRating": "1"
      },
      "reviewBody": "{{review_text}}"
    }
  ]
}
```

### 5.2 Schema Placement

- JSON-LD block injected via `wp_head` on pages/posts where the shortcode or block is rendered
- Only output once per page regardless of how many shortcode instances exist
- Schema output can be disabled globally or per shortcode via `schema="false"` attribute

### 5.3 Business Details for Schema

Stored in plugin settings:

| Setting | Purpose |
|---|---|
| Business Name | `LocalBusiness.name` |
| Street Address | `PostalAddress.streetAddress` |
| City | `PostalAddress.addressLocality` |
| State | `PostalAddress.addressRegion` |
| Postcode | `PostalAddress.postalCode` |
| Country | `PostalAddress.addressCountry` (default: AU) |
| Business Type | Override `@type` (e.g., `Dentist`, `AccountingService`) |
| Google Place ID | Optional, used for `sameAs` link and "Review Us On Google" CTA URL |

**CTA Review Link Construction:**
The "Review Us On Google" button links to `https://search.google.com/local/writereview?placeid={place_id}`. If no Place ID is configured, the CTA button is hidden regardless of the `show_cta` shortcode attribute. The CTA URL can also be overridden manually in settings for edge cases (e.g., branded short URLs).

---

## 6. Frontend Rendering

### 6.1 Shortcode

**Tag:** `[dh_reviews]`

**Attributes:**

| Attribute | Default | Options |
|---|---|---|
| `count` | 5 | 1 to 50 |
| `min_rating` | 1 | 1 to 5 (only show reviews >= this value) |
| `layout` | `grid` | `grid`, `slider`, `list` |
| `columns` | 3 | 1 to 4 (grid layout only) |
| `show_reply` | true | true, false |
| `show_date` | true | true, false |
| `show_photo` | true | true, false |
| `show_stars` | true | true, false |
| `show_aggregate` | true | true, false (renders aggregate bar above reviews) |
| `schema` | true | true, false |
| `location` | (all) | Slug from `dh_review_location` taxonomy |
| `orderby` | `date` | `date`, `rating`, `random` |
| `order` | `DESC` | `ASC`, `DESC` |
| `excerpt_length` | 150 | int (characters, with "Read more" toggle) |
| `show_google_icon` | true | true, false (Google "G" icon on each card) |
| `show_google_attribution` | true | true, false ("powered by Google" under aggregate) |
| `show_cta` | true | true, false ("Review Us On Google" button) |
| `cta_text` | "Review Us On Google" | Custom CTA button text |
| `show_dots` | true | true, false (dot pagination indicators, slider layout only) |
| `visible_cards` | 3 | 1 to 4 (number of cards visible at once in slider) |
| `date_format` | `relative` | `relative` ("4 months ago"), `absolute` ("15 Jan 2025") |
| `class` | (none) | Additional CSS class for the wrapper |

**Example:**
```
[dh_reviews count="6" min_rating="4" layout="slider" show_reply="false" location="perth-cbd"]
```

### 6.2 Gutenberg Block

**Block Name:** `dh/google-reviews`
**Category:** `widgets`

Mirrors all shortcode attributes as block Inspector Controls (sidebar panel). Includes a live preview in the editor using `ServerSideRender`.

### 6.3 Sidebar Widget

Classic WP Widget (for themes still using widget areas). Simplified config: count, min_rating, show_stars, show_aggregate. Always renders in `list` layout.

### 6.4 HTML Structure

```html
<div class="dh-reviews-wrap dh-reviews--{layout}" data-columns="{columns}" data-visible="{visible_cards}">

  <!-- Aggregate Sidebar (if show_aggregate, positioned left in slider layout) -->
  <div class="dh-reviews-aggregate">
    <span class="dh-reviews-aggregate__name">My Bookkeeper Perth Pty Ltd</span>
    <div class="dh-reviews-aggregate__rating">
      <span class="dh-reviews-aggregate__score">5.0</span>
      <span class="dh-reviews-aggregate__stars" aria-label="5.0 out of 5 stars">
        <!-- 5x SVG star icons -->
      </span>
    </div>
    <span class="dh-reviews-aggregate__count">Based on 8 reviews</span>
    <!-- Google Attribution (if show_google_attribution) -->
    <span class="dh-reviews-aggregate__powered">
      powered by
      <svg class="dh-reviews-google-wordmark" aria-label="Google"><!-- Google wordmark SVG --></svg>
    </span>
  </div>

  <!-- Slider Viewport -->
  <div class="dh-reviews-slider">

    <!-- Prev/Next Arrows (slider layout only) -->
    <button class="dh-reviews-slider__prev" aria-label="Previous reviews">
      <svg><!-- Chevron left --></svg>
    </button>

    <!-- Review Cards Track -->
    <div class="dh-reviews-cards">
      <div class="dh-review-card">
        <div class="dh-review-card__header">
          <!-- Avatar: shows photo if available, otherwise initial circle -->
          <div class="dh-review-card__avatar">
            <!-- When photo exists: -->
            <img class="dh-review-card__photo" src="..." alt="..." loading="lazy" />
            <!-- When no photo (fallback): -->
            <span class="dh-review-card__initial" style="background-color: var(--dh-reviews-avatar-color)">S</span>
          </div>
          <!-- Google "G" icon (if show_google_icon) -->
          <svg class="dh-review-card__google-icon" aria-label="Google review">
            <!-- Google "G" icon SVG -->
          </svg>
          <div class="dh-review-card__meta">
            <span class="dh-review-card__name">Sean Morrissey</span>
            <time class="dh-review-card__date" datetime="2025-11-30">4 months ago</time>
          </div>
        </div>
        <span class="dh-review-card__stars" aria-label="5 out of 5 stars">
          <!-- 5x SVG star icons -->
        </span>
        <div class="dh-review-card__body">
          <p>Review text here...</p>
        </div>
        <!-- Owner Reply (if show_reply and reply exists) -->
        <div class="dh-review-card__reply">
          <div class="dh-review-card__reply-header">
            <span class="dh-review-card__reply-label">Response from the owner</span>
            <time class="dh-review-card__reply-date">4 months ago</time>
          </div>
          <p>Reply text here...</p>
        </div>
      </div>
    </div>

    <button class="dh-reviews-slider__next" aria-label="Next reviews">
      <svg><!-- Chevron right --></svg>
    </button>

  </div>

  <!-- Dot Pagination (if show_dots, slider layout only) -->
  <div class="dh-reviews-dots" role="tablist" aria-label="Review pages">
    <button class="dh-reviews-dots__dot dh-reviews-dots__dot--active" role="tab" aria-selected="true" aria-label="Page 1"></button>
    <button class="dh-reviews-dots__dot" role="tab" aria-selected="false" aria-label="Page 2"></button>
    <button class="dh-reviews-dots__dot" role="tab" aria-selected="false" aria-label="Page 3"></button>
  </div>

  <!-- CTA Button (if show_cta) -->
  <div class="dh-reviews-cta">
    <a class="dh-reviews-cta__button" href="https://search.google.com/local/writereview?placeid={{place_id}}" target="_blank" rel="noopener noreferrer">
      <svg class="dh-reviews-cta__google-icon"><!-- Google "G" icon --></svg>
      <span>Review Us On Google</span>
    </a>
  </div>

</div>
```

**Avatar Initial Fallback Logic:**
When `_dh_reviewer_photo` is empty or the photo proxy fails, the plugin generates an initial circle using the first character of `_dh_reviewer_name`. The background colour is deterministic based on a hash of the reviewer name, cycling through a palette of 8 colours (matching Google's own avatar colour set). This is handled in PHP at render time with no JS required.

### 6.5 Styling

**Approach:** Minimal base CSS shipped with the plugin. All visual properties exposed as CSS custom properties for easy theme integration.

**CSS Custom Properties (set on `.dh-reviews-wrap`):**

```css
/* Card Styling */
--dh-reviews-star-color: #FBBC04;
--dh-reviews-star-empty: #E0E0E0;
--dh-reviews-card-bg: #FFFFFF;
--dh-reviews-card-border: 1px solid #E5E7EB;
--dh-reviews-card-radius: 8px;
--dh-reviews-card-shadow: 0 1px 3px rgba(0,0,0,0.08);
--dh-reviews-card-padding: 20px;

/* Typography */
--dh-reviews-font-name: inherit;
--dh-reviews-font-body: inherit;
--dh-reviews-font-size-name: 1rem;
--dh-reviews-font-size-body: 0.9375rem;
--dh-reviews-font-size-date: 0.8125rem;
--dh-reviews-color-name: #1A1A1A;
--dh-reviews-color-body: #4A4A4A;
--dh-reviews-color-date: #9B9B9B;

/* Layout */
--dh-reviews-gap: 20px;
--dh-reviews-max-width: 1200px;

/* Avatar (initial fallback circle) */
--dh-reviews-avatar-size: 48px;
--dh-reviews-avatar-font-size: 1.25rem;
--dh-reviews-avatar-color: #E67E22;
--dh-reviews-avatar-text-color: #FFFFFF;

/* Slider Navigation */
--dh-reviews-arrow-size: 40px;
--dh-reviews-arrow-bg: #FFFFFF;
--dh-reviews-arrow-border: 1px solid #E5E7EB;
--dh-reviews-arrow-shadow: 0 2px 4px rgba(0,0,0,0.1);
--dh-reviews-arrow-color: #333333;
--dh-reviews-arrow-hover-bg: #F9F9F9;

/* Dot Pagination */
--dh-reviews-dot-size: 10px;
--dh-reviews-dot-color: #D1D5DB;
--dh-reviews-dot-active-color: #E67E22;
--dh-reviews-dot-gap: 8px;

/* CTA Button */
--dh-reviews-cta-bg: #E67E22;
--dh-reviews-cta-hover-bg: #D35400;
--dh-reviews-cta-text-color: #FFFFFF;
--dh-reviews-cta-font-size: 1rem;
--dh-reviews-cta-padding: 14px 32px;
--dh-reviews-cta-radius: 6px;

/* Owner Reply */
--dh-reviews-reply-bg: #F9FAFB;
--dh-reviews-reply-border-left: 3px solid #E5E7EB;
--dh-reviews-reply-font-size: 0.875rem;
--dh-reviews-reply-label-weight: 700;

/* Google Icon */
--dh-reviews-google-icon-size: 20px;
```

**Google Avatar Colour Palette (8 colours, matching Google defaults):**

```css
/* Applied deterministically via PHP hash of reviewer name */
--dh-avatar-1: #E67E22;  /* Orange (default) */
--dh-avatar-2: #3498DB;  /* Blue */
--dh-avatar-3: #E74C3C;  /* Red */
--dh-avatar-4: #2ECC71;  /* Green */
--dh-avatar-5: #9B59B6;  /* Purple */
--dh-avatar-6: #1ABC9C;  /* Teal */
--dh-avatar-7: #F39C12;  /* Amber */
--dh-avatar-8: #34495E;  /* Dark slate */
```

**Slider:** Uses lightweight CSS scroll snap (no JavaScript carousel library). Prev/Next arrow buttons trigger `scrollBy` on the container. Dot pagination is calculated in JS based on total cards divided by `visible_cards`. Active dot updates on scroll via `IntersectionObserver`. Clicking a dot scrolls to that page. No jQuery dependency.

**Visible Cards:** The `visible_cards` attribute controls how many cards are shown simultaneously in the slider viewport. Cards are sized using `calc((100% - (gaps)) / visible_cards)` with `flex-shrink: 0`. Responsive override: 2 cards below 768px, 1 card below 480px regardless of the `visible_cards` setting.

**Responsive:** Grid switches to 2 columns below 768px, 1 column below 480px. Slider is touch-native via scroll snap.

**Relative Dates:** When `date_format="relative"` (default), review dates display as "X days/weeks/months/years ago" matching Google's own review display format. Calculated in PHP at render time using the review's `post_date`. Thresholds: <7 days = "X days ago", <5 weeks = "X weeks ago", <12 months = "X months ago", else "X years ago". If `date_format="absolute"`, uses the WordPress site date format setting (`get_option('date_format')`).

### 6.6 Assets and Performance

- CSS: Single file `dh-reviews.css`, enqueued only on pages where shortcode/block is rendered (conditional loading via `has_shortcode()` and block detection)
- JS: Single file `dh-reviews.js` (slider prev/next navigation, dot pagination sync via IntersectionObserver, "Read more" toggle, relative date calculation), loaded with `defer`, no jQuery dependency
- No external font loads
- No third-party CDN resources
- Reviewer photos: Proxied through WordPress if privacy setting enabled (avoids direct Google CDN calls from visitor browsers)
- Estimated payload: <8KB combined (CSS + JS), gzipped

---

## 7. Admin Interface

### 7.1 Menu Structure

```
Reviews (top level, dashicons-star-filled)
├── All Reviews        → CPT list table (dh_review)
├── Add Manual Review  → CPT new post form
├── Import / Export    → CSV import and JSON export
├── Settings           → Plugin configuration
└── Sync Log           → Last 10 sync results
```

### 7.2 Settings Page Sections

**API Connection**
- Google Cloud Client ID (text field)
- Google Cloud Client Secret (password field)
- Connect / Disconnect button (initiates OAuth flow)
- Account selector (populated after OAuth)
- Location selector (populated after account selection)
- Connection status indicator

**Sync Configuration**
- Sync frequency: dropdown (6h / 12h / 24h / manual only)
- Minimum star rating for auto-publish: dropdown (1 to 5, default 1)
- Reviews below threshold: dropdown (`draft` or `do not import`)
- Sync Now button with spinner and result display

**Business Details (for Schema)**
- Business Name, Address fields, Business Type override
- Google Place ID (optional)
- Schema output: enabled/disabled toggle

**Display Defaults**
- Default layout, columns, visible cards (slider), excerpt length
- Show owner replies: toggle
- Show reviewer photos: toggle
- Show Google "G" icon on cards: toggle
- Show "powered by Google" attribution: toggle
- Show "Review Us On Google" CTA button: toggle
- Custom CTA button text: text field
- Date format: dropdown (relative / absolute)
- Show dot pagination (slider): toggle
- Photo proxy: toggle (serve reviewer images through WP instead of direct Google URLs)
- Custom CSS textarea

### 7.3 CSV Import

**Format:**

```csv
reviewer_name,star_rating,review_text,review_date,owner_reply,location
"Jane Smith",5,"Excellent service and very professional.","2025-03-10","Thank you Jane!","perth-cbd"
```

**Import Logic:**
1. Upload CSV via admin form (max 5MB)
2. Parse and validate: required fields are `reviewer_name`, `star_rating`, `review_text`
3. Create `dh_review` CPT posts with `_dh_review_source` = `csv_import`
4. Display import summary (created / skipped / errors)
5. Recalculate aggregate transient

### 7.4 Export

JSON export of all reviews for backup or migration. Includes all meta fields.

---

## 8. Security

| Concern | Mitigation |
|---|---|
| OAuth tokens at rest | Encrypted using `wp_salt('auth')` before storing in `wp_options`. Decrypted only at runtime. |
| XSS in review content | All review text output escaped via `wp_kses_post()`. Reviewer names escaped via `esc_html()`. |
| CSRF on settings | All admin forms use `wp_nonce_field()` / `wp_verify_nonce()`. |
| CSV upload validation | File type check, size limit (5MB), field sanitization on every row. |
| API credentials in source | `.env` pattern supported: if `DH_REVIEWS_CLIENT_ID` and `DH_REVIEWS_CLIENT_SECRET` constants are defined in `wp-config.php`, they override the database values and the fields are hidden in admin. |
| Capability gating | All admin pages require `manage_options`. Review CPT editing requires `edit_posts`. |
| Photo proxy | Optional: reviewer profile photos served through a local endpoint (`/wp-json/dh-reviews/v1/photo/`) to prevent Google tracking pixels in visitor browsers. Photos cached locally for 7 days. |

---

## 9. File Structure

```
dh-google-reviews/
├── dh-google-reviews.php              # Plugin bootstrap, constants, hooks
├── readme.txt                          # WP.org readme (if distributing)
├── uninstall.php                       # Clean removal of options, CPT, transients
├── composer.json                       # google/apiclient dependency (optional, see note)
│
├── includes/
│   ├── class-dh-reviews-activator.php      # Activation: register CPT, schedule cron, set defaults
│   ├── class-dh-reviews-deactivator.php    # Deactivation: clear cron
│   ├── class-dh-reviews-cpt.php            # CPT + taxonomy registration
│   ├── class-dh-reviews-api.php            # GBP API client (auth, token refresh, review fetch)
│   ├── class-dh-reviews-sync.php           # Sync orchestration (cron callback, manual trigger)
│   ├── class-dh-reviews-schema.php         # JSON-LD generation and wp_head injection
│   ├── class-dh-reviews-render.php         # Shortcode + widget rendering logic
│   ├── class-dh-reviews-block.php          # Gutenberg block registration and ServerSideRender
│   ├── class-dh-reviews-import.php         # CSV import handler
│   ├── class-dh-reviews-export.php         # JSON export handler
│   ├── class-dh-reviews-encryption.php     # Token encryption/decryption helpers
│   └── class-dh-reviews-photo-proxy.php    # REST endpoint for proxied reviewer photos
│
├── admin/
│   ├── class-dh-reviews-admin.php          # Admin menu, settings page registration
│   ├── views/
│   │   ├── settings.php                    # Settings page template
│   │   ├── import.php                      # Import page template
│   │   └── sync-log.php                    # Sync log display template
│   └── css/
│       └── dh-reviews-admin.css            # Admin styles
│
├── public/
│   ├── css/
│   │   └── dh-reviews.css                  # Frontend styles with CSS custom properties
│   └── js/
│       └── dh-reviews.js                   # Slider navigation + read more toggle
│
├── blocks/
│   ├── google-reviews/
│   │   ├── block.json                      # Block metadata
│   │   ├── edit.js                         # Editor component (Inspector Controls)
│   │   └── index.js                        # Block registration
│   └── build/                              # Compiled block assets
│
└── templates/
    ├── review-card.php                     # Single review card template (overridable in theme)
    ├── aggregate-bar.php                   # Aggregate rating bar template
    ├── layout-grid.php                     # Grid layout wrapper
    ├── layout-slider.php                   # Slider layout wrapper
    └── layout-list.php                     # List layout wrapper
```

**Note on Google API Client Library:** The `google/apiclient` Composer package is ~20MB and heavy. For a lightweight plugin, implement the OAuth flow and API calls directly using `wp_remote_get()` / `wp_remote_post()`. The GBP API endpoints are simple REST calls. No SDK needed.

---

## 10. Template Overrides

Theme developers can override any template by copying it into their theme:

```
theme/
└── dh-google-reviews/
    ├── review-card.php
    ├── aggregate-bar.php
    └── layout-grid.php
```

The plugin checks for theme overrides first via `locate_template()` before falling back to the plugin's own templates.

---

## 11. WP-CLI Support (Optional, v1.1)

```bash
wp dh-reviews sync                    # Trigger manual sync
wp dh-reviews sync --force            # Force full re-import (ignore updateTime checks)
wp dh-reviews import reviews.csv      # CLI CSV import
wp dh-reviews export --format=json    # Export all reviews
wp dh-reviews stats                   # Show aggregate rating, review count, last sync time
wp dh-reviews flush-cache             # Clear aggregate transients
```

---

## 12. Hooks and Filters (Developer API)

### Filters

| Filter | Purpose | Parameters |
|---|---|---|
| `dh_reviews_query_args` | Modify the WP_Query args before review fetch | `$args` (array) |
| `dh_reviews_card_html` | Filter individual card HTML output | `$html`, `$review_post`, `$atts` |
| `dh_reviews_schema_data` | Filter the JSON-LD array before output | `$schema` (array) |
| `dh_reviews_aggregate_data` | Filter aggregate calculation result | `$aggregate` (array) |
| `dh_reviews_sync_review` | Filter/modify review data before CPT insert | `$review_data` (array) |
| `dh_reviews_css_vars` | Add or override CSS custom properties | `$vars` (array) |
| `dh_reviews_sync_interval` | Override cron interval programmatically | `$seconds` (int) |
| `dh_reviews_min_rating_publish` | Override min star rating for auto-publish | `$min` (int) |

### Actions

| Action | Purpose | Parameters |
|---|---|---|
| `dh_reviews_after_sync` | Fires after a sync completes | `$result` (array: `new`, `updated`, `deleted`, `errors`) |
| `dh_reviews_review_created` | Fires when a new review CPT post is created | `$post_id`, `$review_data` |
| `dh_reviews_before_render` | Fires before the review widget HTML is rendered | `$atts` (shortcode attributes) |

---

## 13. Deployment and Compatibility

### Server Requirements

- PHP 8.1+ (matches current Cloudways stack)
- WordPress 6.4+
- `openssl` PHP extension (for token encryption)
- `curl` PHP extension (for API calls via `wp_remote_*`)
- WP Cron functional (or server-level cron hitting `wp-cron.php`)

### Plugin Conflicts to Account For

- **WP Rocket:** Ensure the JSON-LD script tag in `wp_head` is not deferred or minified incorrectly. Add `dh-reviews.js` to the delay JS exclusion list if using delay execution.
- **Schema plugins (SEOPress, Yoast, RankMath):** If the site already outputs `LocalBusiness` schema from another plugin, the `dh_reviews_schema_data` filter should be used to output only the `review` and `aggregateRating` nodes without duplicating the parent entity. Add a setting toggle: "I already have LocalBusiness schema on this site" which switches output to `aggregateRating` and `review` properties only.
- **Caching plugins:** The shortcode output uses `do_shortcode` at render time. For full-page caches (Varnish, WP Rocket, Breeze), the cached page will show stale reviews until the cache is purged. The `dh_reviews_after_sync` action should trigger a cache purge for pages containing the shortcode. Integrate with WP Rocket's `rocket_clean_post()` and Varnish purge headers where available.
- **Security plugins (Wordfence, Sucuri, Imunify360):** The OAuth callback URL must be whitelisted if WAF rules block unexpected query parameters on admin pages.

### Testing Checklist

- [ ] OAuth flow completes and tokens are stored encrypted
- [ ] Token refresh works after 60-minute expiry
- [ ] Sync creates new review CPT posts correctly
- [ ] Sync updates existing reviews when `updateTime` changes
- [ ] Sync trashes reviews deleted from Google
- [ ] Reviews below min-star threshold are set to draft
- [ ] Shortcode renders all three layouts (grid, slider, list)
- [ ] Gutenberg block renders in editor and frontend
- [ ] JSON-LD schema validates in Google Rich Results Test
- [ ] CSV import handles edge cases (empty fields, special characters, UTF-8)
- [ ] Plugin activation on multisite (single site activation only, not network)
- [ ] Plugin deactivation clears cron but retains data
- [ ] Plugin uninstall removes all data (options, CPT posts, transients, cron)
- [ ] No JS errors on frontend (slider, read more)
- [ ] Responsive layouts at 1200px, 768px, 480px breakpoints
- [ ] WP Rocket compatibility (JS not broken by delay execution)
- [ ] Photo proxy endpoint returns images and caches correctly
- [ ] No PHP warnings at `WP_DEBUG = true`

---

## 14. Roadmap (Post v1)

| Phase | Feature | Priority |
|---|---|---|
| v1.1 | WP-CLI commands | Medium |
| v1.1 | Elementor widget integration | Medium |
| v1.2 | Multi-location aggregation (single widget pulling from multiple GBP locations) | High |
| v1.2 | Review notification emails (new review alert to site admin) | Low |
| v1.3 | Review badge/floating widget (fixed position CTA showing aggregate score) | Medium |
| v1.3 | A/B layout testing (rotate grid vs slider, measure CTA clicks) | Low |
| v2.0 | Additional review sources (Facebook, Trustpilot) via modular adapters | High |
| v2.0 | Central dashboard (Cloudways hosted) aggregating reviews across all DH client sites | High |

---

## 15. Development Notes for Claude Code

### Key Decisions Pre-Made

1. **No Google API PHP SDK.** Use `wp_remote_get()` / `wp_remote_post()` directly. Keep the plugin lightweight.
2. **No jQuery.** Vanilla JS only. The slider uses CSS `scroll-snap-type` with `scrollBy()` for navigation.
3. **No React for the frontend.** The Gutenberg block uses `ServerSideRender` backed by the same PHP render function as the shortcode. The block editor controls use `@wordpress/components` (InspectorControls).
4. **CPT is the single source of truth.** Once reviews are synced from the API, all rendering reads from the CPT. If the API is down, reviews still display.
5. **Template override pattern follows WooCommerce conventions.** Theme developers familiar with WooCommerce will understand the override structure immediately.
6. **Star rendering uses inline SVG, not icon fonts.** A single SVG star shape is reused with `fill` controlled by CSS. No Font Awesome or Dashicons dependency on the frontend.
7. **The plugin must work without the API connected.** Manual review entry and CSV import are fully functional standalone features. The GBP API integration is an optional enhancement.

### Build Tooling

- Block assets compiled with `@wordpress/scripts` (`wp-scripts build`)
- No frontend build step for CSS/JS (ship source directly, keep it simple)
- PHP follows WordPress Coding Standards (WPCS)
- Namespace: `DH_Reviews` (PHP classes), `dh/google-reviews` (block)
