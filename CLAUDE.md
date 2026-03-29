# DH Google Reviews Widget

## Context
WordPress plugin for displaying Google Business Profile reviews.
Full specification is in SPEC.md. Always reference it before making
architectural decisions.

## Tech Stack
PHP 8.1+, WordPress 6.4+, vanilla JS (no jQuery), CSS custom
properties, @wordpress/scripts for Gutenberg block build.

## Conventions
WordPress Coding Standards (WPCS) for all PHP.
BEM naming for CSS classes (dh-reviews-card__header pattern).
No dashes in any user facing text or comments (agency style rule).
All PHP classes namespaced under DH_Reviews.
Use wp_remote_get/wp_remote_post for HTTP, not cURL directly.
Escape all output: esc_html(), esc_attr(), wp_kses_post().
Nonce all admin forms.

## Commands
wp-scripts build (from /blocks directory for Gutenberg assets)
No frontend build step for public CSS/JS.

## File Structure
See SPEC.md Section 9 for complete file tree.

## Important
Never use the Google API PHP SDK. All API calls use wp_remote_*.
Star rendering uses inline SVG, not icon fonts.
CPT is the single source of truth for all review data.
Plugin must work fully without API connected (manual entry mode).
```

## Step 3: Go to Claude Code on the web

Navigate to [claude.ai/code](https://claude.ai/code). You select a GitHub repository, describe what you want done, and Claude works on the task in a remote environment. 

Connect your GitHub account if you haven't already. Claude clones your repository and runs any configured setup script. Claude Code runs to complete your task, writing code, running tests, and checking its work. You can guide and steer Claude throughout the session via the web interface. 

Select the `dh-google-reviews` repository.

## Step 4: Feed it tasks one at a time

Once Claude completes its work, it pushes the changes to a new branch in your GitHub repository. You can review the changes, then create a pull request directly from the interface. 

Start a new session for each major chunk of work. Here's the sequence:

**Session 1: Scaffold**
```
Read SPEC.md thoroughly. Scaffold the complete file structure 
from Section 9, creating all files with class stubs, proper 
WordPress hooks, and PHPDoc headers. Do not implement logic 
yet, just the skeleton with correct namespacing and hook 
registration. Reference CLAUDE.md for conventions.
```

Merge the PR, then start a new session:

**Session 2: CPT and data model**
```
Implement class-dh-reviews-cpt.php fully. Register the 
dh_review CPT and dh_review_location taxonomy per Section 4 
of SPEC.md. Include all meta field registration with 
register_post_meta() and admin columns for the list table 
(star rating, source, date, location).
```

**Session 3: Render engine and templates**
```
Implement class-dh-reviews-render.php with the shortcode 
registration and all template rendering per Section 6 of 
SPEC.md. Build the three layout templates (grid, slider, 
list), the review card template, and the aggregate bar 
template. Match the exact HTML structure from Section 6.4.
```

**Session 4: Frontend CSS and JS**
```
Create public/css/dh-reviews.css with all CSS custom 
properties from Section 6.5 of SPEC.md and base styling for 
grid, slider, and list layouts. Create public/js/dh-reviews.js 
with slider scroll snap navigation, dot pagination sync via 
IntersectionObserver, and the read more toggle. No jQuery.
```

**Session 5: Schema output**
```
Implement class-dh-reviews-schema.php per Section 5 of 
SPEC.md. JSON-LD generation injected via wp_head, with the 
LocalBusiness wrapper and the toggle for sites that already 
output LocalBusiness schema from SEOPress.
```

**Session 6: Admin settings**
```
Implement the admin settings pages per Section 7 of SPEC.md. 
API connection fields, sync configuration, business details 
for schema, and display defaults. Use the WordPress Settings 
API.
```

**Session 7: OAuth and API sync**
```
Implement class-dh-reviews-api.php and 
class-dh-reviews-sync.php per Sections 3.1 through 3.3 of 
SPEC.md. OAuth 2.0 flow with encrypted token storage, token 
refresh, review fetching with pagination, and WP Cron sync 
with deduplication.
```

**Session 8: CSV import and manual entry**
```
Implement class-dh-reviews-import.php for CSV import per 
Section 7.3 of SPEC.md, and the manual review entry admin 
form. Include validation, error handling, and aggregate 
recalculation after import.
```

**Session 9: Gutenberg block**
```
Implement the Gutenberg block per Section 6.2 of SPEC.md. 
Block registration in block.json, editor component with 
InspectorControls mirroring all shortcode attributes, and 
ServerSideRender for live preview.
