# Palefire

Drupal 11 theme for LSU Libraries (lib.lsu.edu), generated from starterkit
and built on Bootstrap 5.3, Dart Sass and Swup 4. The latest build is live
at https://lib.lsu.edu/v2/special.

- [Build](#build)
- [Deploy](#deploy)
- [Repository layout](#repository-layout)
- [Setup scripts](#setup-scripts)
- [Modular pages](#modular-pages)
- [Landing sections](#landing-sections)
- [Building maps](#building-maps)
- [Tile showcase](#tile-showcase)
- [Events carousel](#events-carousel)
- [Guide pages](#guide-pages)
- [Exhibition pages](#exhibition-pages)
- [Reading colours and Settings](#reading-colours-and-settings)
- [Header, menu and drawers](#header-menu-and-drawers)
- [Page transitions (Swup 4)](#page-transitions-swup-4)
- [Design conventions](#design-conventions)
- [Changelog](#changelog)

## Build

    npm install          # bootstrap, bootstrap-icons, swup, sass
    npm run build        # build:css + copy:assets
    npm run build:css    # scss/style.scss -> css/style.css (compressed)
    npm run watch        # recompile on save

`node_modules/` is not committed. Production only needs the build output in
the repo: `css/style.css`, `js/vendor/` and `images/bi/`. Commit the rebuilt
`css/style.css` with every SCSS change.

`css/style.css` is one minified line, so a patch hunk for it fails whenever
any other style change is uncommitted. If a patch leaves it out, run
`npm run build:css` after applying.

## Deploy

```sh
git pull
drush cr
```

- Staging (libstage) receives changes downstream from production.
- Drupal config (fields, paragraph types, displays) is changed by hand in the
  production UI or by the [setup scripts](#setup-scripts). This repo never
  exports config YAML.
- `drush cr` also refreshes the CSS/JS cache-busting hashes (below).
- **Font Awesome Pro** is licensed, so it is not in this public repo. It lives
  in the site's `/libraries/fontawesome-pro` (Drupal root, including `svgs/`).
  Templates inline its SVGs with `source('libraries/fontawesome-pro/svgs/…',
  ignore_missing = true)`, so no page loads the FA stylesheet or webfont.
  Only FA 5 names are used. When a change adds an icon, check that its SVG
  exists on production and libstage.

### Cache busting

`palefire_library_info_alter()` (`palefire.theme`) sets each library's
version to a 10-character hash of its files' contents, so a changed file gets
a new URL (`style.css?v=<hash>`, or a new `css_….css` aggregate name) and
unchanged files keep theirs. The versions in `palefire.libraries.yml` are only
labels; don't bump them. Files outside the theme and external URLs keep their
declared versions.

## Repository layout

| Path | What |
| --- | --- |
| `palefire.info.yml` | Regions, global libraries, `core_version_requirement: ^11` |
| `palefire.breakpoints.yml` | Bootstrap 5 breakpoints (sm 576 / md 768 / lg 992 / xl 1200 / xxl 1400) |
| `palefire.theme` | Preprocess functions and helpers (`_palefire_*`) |
| `src/Hook/PalefireHooks.php` | OOP theme hooks: TOC, section anchors, page head, landing bundles |
| `scss/` | `_variables.scss` (brand + Bootstrap overrides), `base/`, `layout/`, `components/` |
| `components/toc/` | Single Directory Component: "On this page" navigation |
| `templates/paragraphs/` | Section and block markup for Modular pages |
| `templates/includes/` | Shared partials: logo, utility panels, landing, Visit strip, map key, building and parish drawings, guide head, request paths |
| `templates/navigation/palefire-modular-toc.html.twig` | The TOC. Keep exactly one copy of this file in the theme; a stray copy elsewhere wins the registry |
| `maps/buildings.yml` | Building map definitions |
| `js/` | Behaviours, one library each, attached only where needed |
| `build/copy-assets.js` | Copies Bootstrap JS, Swup and the Bootstrap Icons the theme uses |
| `scripts/` | One-time `drush php:script` setup and content scripts (below) |
| `patches/` | Patches for review (gitignored) |

## Setup scripts

Run from the Drupal root with
`drush php:script themes/custom/palefire/scripts/<script>`, then `drush cr`.
All setup scripts only add what's missing and are safe to re-run.

| Script | Does |
| --- | --- |
| `palefire_visit_setup.php` | Visit fields on Modular page and the `light-map` section theme |
| `palefire_building_map_setup.php` | Building spaces vocabulary, building map paragraph types and fields, missing building/room terms. Re-run after editing `buildings.yml`. `-- cleanup` deletes the old `map_space` type |
| `palefire_landing_hero_setup.php` | `landing_hero` paragraph type |
| `palefire_landing_hero_tiles_setup.php` | Hero tile type, Tiles and Blog categories on Landing hero |
| `palefire_landing_department_setup.php` | `landing_department` type (run after the hero tiles script) |
| `palefire_tile_showcase_setup.php` | `tile_showcase` type, its image styles and responsive image styles |
| `palefire_landing_stacks_setup.php` | `landing_stacks` type (From the stacks): Featured item, Blog categories, LibCal calendar and campus (run after the tile showcase and hero tiles scripts) |
| `palefire_guide_setup.php` | Guide content model: Resource type, guide paragraph types, guide fields (incl. Guide editors) |
| `palefire_louisiana_newspapers_setup.php` | `parish_map`, `parish_entry`, `family_trees` paragraph types |
| `palefire_guide_wysiwyg.php` | Moves existing guides' text to the WYSIWYG format (dry run unless `-- apply=1`) |
| `palefire_exhibit_setup.php` | Exhibition content model: `exhibition` mode, title page fields, `exhibit_*` paragraph types, `exhibit_*` image styles, the Sources text style (after the guide setup) |
| `palefire_exhibit_aves_lead_captions.php` | Fills the 9 draft AVES lead captions on the existing page (empty ones only; stops on a published page unless `-- force=1`) |
| `palefire_exhibit_aves_update.php` | Brings the live AVES page up to `data/aves.json`: Capital Case names, "Main Library", the 16 images from `Section 4 (additions)`, catalogue lead captions (`-- images="<AVES exhibition 4K folder>"`). Keeps editors' own changes |
| `palefire_exhibit_aves_typos.php` | Corrects 25 typos in the live AVES page (missing spaces and brackets in names, hyphen and dash spacing, Vieillot's dates, Weimar, Chubb), the same as in `data/aves.json`. Exact replacements only; lists any it can't find |
| `palefire_exhibit_aves.php` | Builds AVES as an unpublished exhibition (`-- images="<AVES exhibition 4K folder>" path=/v2/exhibitions/aves`), importing the 4K images into Media |
| `palefire_exhibit_occult.php` | Builds Occult Science and Philosophy in the Renaissance as an unpublished exhibition (`-- images="<Occult Science 4K folder>" path=/v2/exhibitions/occult`), importing its 40 images into Media |
| `palefire_exhibit_audubon.php` | Builds John James Audubon in Louisiana as an unpublished exhibition (`-- images=<Audubon 4K folder> path=/v2/exhibitions/audubon`), importing its 93 images into Media |
| `palefire_exhibit_replace_image.php` | Swaps an Image media item's file for a new one (`-- media="<media name>" file=<path>`), keeping the item, its alt text and every figure using it. For a better scan found after an exhibition was built |
| `palefire_exhibit_audubon_wall.php` | Sets the already-built Audubon page's Elephant Folio, Octavo Edition and Drawings and Proofs to Display "wall", their sheet sizes, the pairs' "Drawing" / "Proof" captions and the drawings' alignments (empty ones only). Run `palefire_exhibit_setup.php` first |
| `palefire_section_theme_check.php` | Read-only: lists theme options and each section's saved theme, e.g. `… /special` |
| `palefire_guide_demo.php`, `_research_help.php`, `_special_collections.php`, `_louisiana_newspapers.php` | Each builds one guide as an unpublished page (`-- path=/v2/guides/…`). One-time starting points: they stop if a page with the same title exists and never update one |
| `palefire_modular_setup.php` | Legacy: only for sites **without** palefire_modular; exits otherwise |
| `palefire_modular_setup_undo.php` | Removes what a partial legacy run left on a palefire_modular site |

## Modular pages

Any paragraph type with `field_section_title` in `field_sections` on a
`modular_page` node becomes a `<section>` with an H2, a stable anchor and a TOC
entry. `<html data-pf-mode="…">` carries the page's Type (`guide`, `landing`…).

### Page head

`page.html.twig` prints `<header class="pf-modular__head">` above the first
section: the breadcrumb (styled like the TOC, `.pf-breadcrumb--modular`) and
the node title as the only `<h1>` (a placed Page title block is emptied on
Modular pages). When a landing opens the page, the landing prints the `<h1>`
and its own breadcrumb bar instead (`PalefireHooks::titleLandingUuid()`,
`crumbInLanding()`). The `<h1>` uses `$pf-display-weight` (600).

### Table of contents

- From lg the TOC is a sticky sidebar; below lg a list-icon button opens it as
  an offcanvas (`<html data-pf-panel="toc">` gives it the drawers' backdrop).
- Its visible title is "Table of contents", a plain label that names the
  `<nav>`.
- **Hide/show** (lg and up, not on guides): `js/modular-toc-collapse.js`, loaded
  in `<head>` so there is no flash. Pages that open with a Landing hero
  (`<html data-pf-hero>`) start hidden, every other page shown; a visitor's
  choice is saved per page type (`palefire.tocCollapsed.hero` /
  `palefire.tocCollapsed.landing` / `palefire.tocCollapsed`).
- **Listing:** sections, plus h3s on modes in the block's "Include h3
  sub-links" setting (`deep_modes`, default `guide`); elsewhere only
  `[data-toc]`. Attributes: `data-toc`, `data-toc-label` (short link text),
  `data-toc-detail` (extra screen-reader text), `data-toc-passive` (listed,
  not scroll-highlighted), `data-toc-skip` (area left out). A component that
  renders late dispatches a bubbling `palefire:tocrefresh` to rebuild its
  section's sub-list.
- **Landings:** `PalefireHooks::heroTocIds()` adds Home (`#home`, with
  "Landing" and "Quick guide" sub-links on the hero) and Map (`#map`). A page
  whose only section is its landing gets the TOC from `landing_toc`.
- **Active section:** switches when a section reaches three-quarters down the
  screen (`ACTIVATION_LINE`, `js/modular-toc.js`). The gold bar covers only the
  active link. On guides the current heading lights up with it
  (`.is-toc-current`).
- **Jumps:** `Drupal.palefireGlide()` (`palefire.js`), an eased 600–1100ms
  glide, interruptible, instant under reduced motion. Clicks update the hash
  with `replaceState` (no history entries); the first link and "Back to top"
  go to the very top and clear the hash. Focus moves to the target heading.

### Backgrounds and glass

Section backgrounds (`field_section_bg`) and landing videos bleed under the
sidebar and the top bar, which are frosted glass. `palefireBleedOffset`
(`palefire.js`) measures the reach. Glass tokens: `$pf-glass-blur`,
`$pf-glass-saturate`, `$pf-header-bg-alpha`, and `sidebar-bg` in
`_modular-tokens.scss`. `prefers-reduced-transparency`, `prefers-contrast: more`
and the matching Settings switches make the chrome solid (`pf-solid-chrome`
mixin).

### Scroll snap and motion

- `field_full_page` / `field_section_snap` turn snapping on, md and up only
  (`$pf-snap-breakpoint`). With proximity snapping the snap point is a 1px
  marker at each section's top, so tall sections scroll freely.
- `js/section-reveal.js` slides section H2s in as they enter the view (not on
  guides). Off with reduced motion.
- Images in content shrink to their column (`max-width: 100%`) and keep
  their width/height attributes.

## Landing sections

Three paragraph types share one markup, `templates/includes/landing.html.twig`,
listed in `PalefireHooks::LANDING_BUNDLES`:

| Type | Use |
| --- | --- |
| `bento_search` | The original landing with a Bento search form (retired, still renders) |
| `landing_hero` | Special Collections hero: lead panel, tiles, Quick Guide, map, Visit strip |
| `landing_department` | Bento-style landing with the hero's tile accordion (Oral History) |

"Bento" was the prototype name. The theme's classes, JS and SCSS say
"landing" (`.pf-landing*`, `landing-video.js`, `_landing*.scss`), while Drupal
machine names keep `bento` (`bento_search`, `field_bento_map`,
`field_bento_hide_lead`, `palefire_preprocess_paragraph__bento_search()`). The
real search still goes to `https://lib.lsu.edu/bento` with
`search_api_fulltext_bento`.

### Fields

Visit fields on `node.modular_page` (all optional; hide them in the node's view
display, the landing prints them):

| Field | Type | Used for |
| --- | --- | --- |
| `field_intro` | Text (formatted, long) | Intro in the lead panel |
| `field_hours_location` | List: LibCal location ID | Status line, week hours, hero's "Open now" line |
| `field_location_name` | Text (plain) | Visit heading |
| `field_location_query` | Text (plain) | Google Maps link and embed |
| `field_location_display` | List: `link` / `embed` | Link only, or link + mini map |

LibCal IDs: `241` (LibCal's "LSU Library", shown as Main Library) and `236`
(Hill Memorial Library). /v2/special should use 236.

On the paragraphs:

| Field | On | Notes |
| --- | --- | --- |
| `field_bento_hide_lead` | Bento search, Landing hero | "Hide lead panel": open on the map alone (only when there is a map) |
| `field_hero_tiles` | Landing hero, Landing department | Up to three Hero tiles (Modular page, optional label and dates). Image: the page's Image Cover, else its landing poster |
| `field_landing_blog_category` | Landing hero | Article Categories; shows the newest article in any of them |
| `field_quick_guide_title`, `_intro`, `_link` | Landing hero | Quick Guide heading, intro and "See full guide" link; empty keeps the default text |

### What the hero shows

- **Bar:** breadcrumb, "Sign in" (Aeon, new tab; the icon alone on phones)
  and the background-video button, sticky under the header for the whole
  landing.
- **Lead panel** (full screen from md, `.pf-landing__lead--hero`): title and
  intro (side by side from xl; keep the intro to a sentence or two), a 4rem
  search field with an accent-filled Search button and suggested searches
  under it (the optional Landing hero field `field_search_suggestions`, Text
  (plain), any number of values; without it, built-in ones), then At a
  glance (`.pf-today`): today's hours (`js/visit-hours.js`), Next
  event (`js/hero-event.js`, from the events carousel's or From the stacks'
  calendar; "No upcoming events" when there are none), the exhibitions on
  view, and the latest blog post. Every cell is a label, one value line and
  one muted line; four across when the panel is 64rem wide, two by two from
  30rem, stacked below. The Quick Guide sits at its foot.
- **Quick Guide:** the request paths from
  `includes/guide-request-paths.html.twig`, shared with the full guide: three
  options A, B, C, each a line of stops. Edit the stops there and both pages
  change. "See full guide" under them. The space around its star rule is
  tighter in the hero, so the guide follows At a glance closely.
- **Map and Visit strip:** the building map with its key and the Visit strip
  (`includes/visit-strip.html.twig`) in one glass panel.
- **On view** (`.pf-landing__onview`): under the map, so the galleries come
  before what's in them. The tile accordion (`includes/landing-exhibits.html.twig`;
  `js/exhibit-rotate.js` crossfades multi-image tiles with a pause button) on
  the lead's glass.

### Background video

`js/landing-video.js`. With two or more videos, two `<video>` layers
crossfade so there is never black between clips (`--pf-landing-fade` and
`FADE_MS`, keep equal). The button pauses/plays both, shows a progress line and
pauses the clip off screen. The video layer is fixed to the viewport and
clipped at the section's foot. Reduced motion and Settings > Pause background
videos start it paused.

### Lead → map

- `js/landing-snap.js`: from md, a wheel/key step down from the lead's end
  glides to the map and back up. A lead taller than the window scrolls freely
  until its end is halfway up the window. Touch and phones are left to the
  browser.
- The map holds still while the lead slides off it (`position: sticky` with
  `--pf-map-reveal-hold`, `_landing-hero.scss`), where
  `animation-timeline: view()` is supported, from md, without reduced motion.

### "Light, dark at the map"

The `light-map` section theme (added by `palefire_visit_setup.php`) starts
light; when the map comes into view, `building-map.js` adds `.is-map-dark` and
`data-bs-theme="dark"` and sends `palefire:mapview`, and the header and sidebar
follow. It waits for the first scroll, so a page never flashes dark on load.
If a page is dark from the top, check the saved theme with
`palefire_section_theme_check.php`.

## Building maps

A landing shows a floor map through `field_bento_map` → `building_map`.
Each building is one drawing (`templates/includes/*-map.svg.twig`) plus an
entry in `maps/buildings.yml` (label, heading, include, rooms with floor,
`legend` icons). Content lives in the **Building spaces** taxonomy: one term
per building (its description is the note above the legend) with a child term
per room (tooltip, access, optional link). Unpublish a room to draw it as
plain floor.

- **Paragraph fields:** building, Intro (`field_map_intro`, the summary under
  the heading), Room overrides (one room's tooltip/access/link on this page),
  You are here (`field_map_here`: arrow, "Here" badge,
  `aria-current="location"`).
- **To add a building:** drawing under `templates/includes/`, entry in
  `buildings.yml`, re-run `palefire_building_map_setup.php`.
- **Behaviour** (`js/building-map.js`, `_building-map.scss`): rooms light at
  once on hover; the tooltip waits `--pf-map-tip-delay` (1.75s), or opens at
  once on focus or tap. Rooms with links are links, others are buttons, and
  tooltips reach screen readers via `aria-describedby`. The outlines trace and
  rooms fade in the first time a quarter of the map is in view
  (`SETTINGS.duration`, `stagger`, `reveal*`; `--pf-map-reveal-*`).
  Appointment rooms get a broken hatch. Legend swatches follow the map's hatch
  at any size (`--pf-map-hatch-*`).
- "Legend & spaces" is a `<details>` that opens like the guide accordions.
- The map heading is an h2 on the hero, h3 elsewhere; it carries `data-toc`.

## Tile showcase

`tile_showcase` (How-to guides, Collections, Specializations): the section
fields plus `field_tile_layout` (`stagger` / `grid` / `index`) and
`field_tiles` (Tile nodes or Modular pages, read by `_palefire_node_card()`).
`index` is a numbered list with no pictures. Each card: the title (h3), the
cover (a Tile's Tile Cover; a Modular page's Image cover or landing poster)
through the
`tile_showcase_portrait`/`_landscape` responsive styles, its Category terms as
the label, and its link. Layouts use container queries; the staggered row is a
swipeable strip under 60rem. `js/tile-reveal.js` animates cards in on scroll
(nothing hidden without JS or with reduced motion). A scrim keeps white titles
at 4.5:1 on any image.

## From the stacks

`landing_stacks` (`paragraph--landing-stacks.html.twig`, `_stacks.scss`): the
section fields plus the Featured item (`field_tiles`; the first Tile or
Modular page shows, with its cover, title, Category terms and intro), Blog
categories (`field_landing_blog_category`; the newest three articles) and
the events list's LibCal calendar and campus (`field_stacks_calendar`,
`field_stacks_campus`). `js/upcoming.js` lists the next three timed events at
that campus from the events carousel's feed, reading up to three two-week
windows (the feed drops its campus filter after the first, so events are
matched by `campusId`), and only once the list is near the window. With no
events it says so. Its calendar also feeds the hero's Next event cell, so the
events carousel can come off the page.

## Events carousel

`_events-carousel.scss` restyles the `libcal_gantt_carousel` block in the
Swiss style, theme CSS only (colours through its `--ldc-*` properties, every
selector under `.pf-events-carousel`). Day strip with an accent bar, square
prev/next buttons, ruled hours and event lists, image cards with events over a
gradient, and the module's "On this day" fact as a muted footnote.
`section-reveal.js` reveals its parts in sequence. The next three timed events
are listed in the TOC through `palefire:tocrefresh`.

## Guide pages

A Modular page with **Type = guide** (`field_modular_type`) holds a whole
research guide on one page: every LibGuide tab becomes a section, every box a
block, and "On this page" lists each block heading (h3), replacing the tab
menu.

### Set up (production, once)

```bash
drush php:script themes/custom/palefire/scripts/palefire_guide_setup.php
drush cr
```

The script finds the Staff type and Subjects vocabulary by name; if unsure it
lists candidates (re-run with `-- staff=<type> vocab=<vocabulary>`). It never
changes an existing view display. Then:

- **Permissions:** give guide editors "Resource: Create new content" and "Edit
  any content".
- **Text format:** guide text uses WYSIWYG, which must allow `<table>`,
  `<caption>`, `<th scope>`, `<h4>`, `<ol>`, `<ul>`, `<code>` and `<a href>`.
  The guide scripts stop if there is no WYSIWYG format (`-- format=<name>`
  picks another).
- **Guide card:** check Staff > Manage display > Guide card.

### Content model

`node.modular_page` guide fields (printed in the page head by
`PalefireHooks::guideHead()` / `includes/guide-head.html.twig`, hidden in the
node display):

| Field | Type | Used for |
| --- | --- | --- |
| `field_modular_type` | existing | `guide` turns on guide mode |
| `field_intro` | existing | The lede under the `<h1>` |
| `field_guide_owner` | Entity reference → Staff | "Guide by" |
| `field_guide_editors` | Entity reference → Staff, unlimited | "Edited by A, B and C" (owner and inaccessible pages skipped) |
| `field_guide_subjects` | Entity reference → Subjects | Subject tags |
| `field_guide_source` | Link | The LibGuide it replaces (not shown) |

**Resource** (`node.resource`): one link, database, book or tool shared by
every guide that lists it. Its own page is `noindex`.

| Field | Type | Notes |
| --- | --- | --- |
| `title` | | Link text |
| `field_resource_link` | Link | Plain address |
| `field_resource_proxy` | Boolean | "Requires LSU login": adds the libezp prefix (`PALEFIRE_PROXY_PREFIX`) |
| `field_resource_type` | List | Picks the icon (`PALEFIRE_RESOURCE_ICONS`) |
| `field_resource_citation` | Text | Line under the title |
| `field_resource_desc` | Long text (plain) | One or two sentences |
| `field_resource_labels` | Text, up to 3 | Free, Paid, Online… |

Paragraph types:

| Type | Fields |
| --- | --- |
| `guide_section` | Shared section fields + `field_guide_blocks` (no snap/height fields; its template must not extend `paragraph.html.twig`) |
| `guide_text` | `field_block_title`, `field_block_body`, `field_text_style` (plain / callout) |
| `resource_list` | `field_block_title`, `field_block_intro`, `field_resources` |
| `guide_accordion` / `accordion_item` | Block title and intro / item title, body, resources |
| `librarian_card` | `field_block_title`, `field_librarian` (→ Staff) |
| `guide_video` | `field_block_title`, `field_video` (Remote video; player loads only on Play, nocookie/`dnt=1`) |
| `parish_map` / `parish_entry` / `family_trees` | Louisiana Newspapers only (below) |

View modes: `node.guide_item` (Resource in a list) and `node.guide_card`
(Staff).

### Writing a guide

- One section per LibGuide tab; subpages become blocks.
- Block headings are h3 and appear in the TOC. Inside a block use
  **Heading 4**. Accordion contents are not listed.
- Add Resources first, then pick them by title. Paste the plain address and
  tick "Requires LSU login" instead of a libezp link.
- A paragraph that is only `[request-paths]` draws the Special Collections
  request diagram after the block's text. Its stops, "how" text and
  screenshots live in `templates/includes/guide-request-paths.html.twig`.
- A bulleted list with a nested list is drawn as a decision tree.
- Numbered lists become steps; a leading **bold** phrase is the step's title.
- No "Next / Previous" text: the TOC does navigation.

### Look and behaviour

- Styles in `_guide.scss`, colours from the reading-colour tokens (`--pf-g-*`)
  and `--bs-*`; no literal colours.
- Accordions are native `<details>` and open with a staggered fade
  (`palefireAccordionSequence`).
- Tables scroll inside their block on narrow screens. Icons are inline
  Bootstrap Icons SVGs.
- Settings > Reader font for guides switches guides to a system book serif.
- Keep the class names (`.pf-resource`, `.pf-accordion__*`,
  `.pf-guide-block__title`, `.pf-label`) stable: a LibGuides group stylesheet
  is meant to reuse `_guide.scss`.

### The guides

- **Systematic Reviews** (`palefire_guide_demo.php`): the first, demo guide.
- **How to Request Research Help** (`palefire_guide_research_help.php`): from
  lib.lsu.edu/llms.txt, with `[Editor: …]` notes to fill before publishing.
- **How to Use Special Collections** (`palefire_guide_special_collections.php`):
  requesting and visiting (request paths), finding aids, collection types and
  Ask Us. The Ask Us example uses an invented collection on purpose.
  - **Request paths** (`guide-request-paths.html.twig`, also in the landing's
    Quick Guide): a stop with `detail` has a dotted-underlined label that opens
    a native popover (no script) with a sentence or two and, with `shots`,
    screenshots from `images/guides/special-collections/` (WebP, lazy, width
    and height set, so they load only when opened). Esc, the close button or a
    click outside closes it; focus returns to the label. Screenshots come from
    the unpublished "How to Request Materials" and "How to Submit a
    Duplication Request" LibGuide pages (September 2026), cropped to the part
    that matters. Replace a file under the same name when the catalog or
    request system changes.
- **Louisiana Newspapers** (`palefire_guide_louisiana_newspapers.php`, after
  `palefire_louisiana_newspapers_setup.php`): the LibGuide word for word, with
  a clickable map of the 64 parishes.
  - **Parish map** (`js/parish-map.js`, `_parish-map.scss`): one Parish item per
    parish with three WYSIWYG fields; each panel is a `<template>` until
    chosen. Map, Parish menu and list stay in step; list and map light each
    other on hover. The URL keeps the parish (`#parish-orleans`). Side by
    side, the map sticks `--pf-parishes-stick-gap` (2–5rem, by window height)
    under the header and shrinks to fit short windows. In the panel, a linked
    entry's description is muted and a size smaller than its title.
  - **Family trees** are never typed in: `_palefire_newspaper_trees()` reads
    the newspaper records with one database query (any bundle with
    `field_louisiana_parish` and `field_record_url`), and logs and skips on
    failure. The **Family trees** block searches and filters them
    (`js/family-trees.js`).
  - **Tree viewer** (`js/family-tree-viewer.js`): a tree link opens a
    full-screen `<dialog>` that draws the page's JointJS JSON as SVG (JointJS
    is not loaded). Shareable `?tree=<page name>` links; Back closes it.
    Modified clicks and pages without a diagram fall through to the old page.

### Move existing guides to WYSIWYG

```bash
drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php
drush php:script themes/custom/palefire/scripts/palefire_guide_wysiwyg.php -- apply=1
```

The first run lists what would change; `apply=1` saves each guide as a new
revision. It stops if WYSIWYG lacks the tags above (`force=1` overrides).

## Exhibition pages

A Modular page with **Type = exhibition** holds an online exhibition: a
title page, then one gallery (section) at a time, chosen from the TOC.

### Set up (production, once)

```bash
drush php:script themes/custom/palefire/scripts/palefire_exhibit_setup.php
drush cr
```

Run `palefire_guide_setup.php` first (exhibitions reuse its blocks and
`field_guide_blocks`). Give editors "Create new media" for images.

### Content model

On `node.modular_page` (exhibition mode only, printed by
`includes/exhibit-cover.html.twig`): `field_exhibit_subtitle`,
`field_exhibit_cover` (Media: Image, decorative), `field_exhibit_credit`,
`field_exhibit_prompt` (default "Choose a gallery from the table of contents
to begin."), and `field_intro` if filled. Optional tiles under the credit:
`field_exhibit_year` (a number) and `field_exhibit_physical_note` (a short
note on the physical exhibition). The note sits behind a tile with FA Pro's
light treasure chest, inlined from
`/libraries/fontawesome-pro/svgs/light/treasure-chest.svg` (the words "On
display" where the server has no FA Pro files). The tile is a button
described by the note, which shows as a tooltip on hover, keyboard focus or
a tap, can itself be hovered, and hides with Esc (WCAG 1.4.13,
`js/exhibition.js`).

| Paragraph type | Fields | Notes |
| --- | --- | --- |
| `exhibit_page` (Gallery) | Title, anchor, TOC hide, `field_exhibit_opener`, `field_exhibit_lead` + `_lead_focus` + `_lead_whole` + `_lead_caption`, `field_exhibit_display`, `field_exhibit_sheet`, `field_guide_blocks` | A section. Opening **Cover** (default): the lead fills the screen and stays put, with the number, title, "In this gallery" links and opening text on it. **Full screen, then pan**: the lead fills the screen with the number, title and links; scrolling, it stays pinned while they fade, then pans through the whole image before the text. **Compact**: a band above the title (scroll-pan, or "Show whole image"). With a caption the lead is a figure (alt text, lightbox, caption); without, decorative. Display **Story** (default) or **Wall** (below); Sheet size ("40 × 27", inches) for a wall's "Actual size" |
| `exhibit_entry` (Entry) | `field_block_title` (h3, TOC sub-link), `field_section_anchor`, `field_entry_bio`, `field_guide_blocks` | A person or book; the second level of nesting under the TOC |
| `exhibit_figure` (Image) | `field_figure_image`, `field_figure_caption`, `field_figure_layout`, `field_figure_align` | Layouts: beside the text (image first / second), centred, full width (parallax). Opens the lightbox. Alignment ("k rot tx ty": scale, degrees, left and top in proof widths) lays a drawing over the proof after it in a wall's comparison |
| `exhibit_gallery` (Image group) | Optional heading, `field_gallery_items` (figures), group caption | Grid; each figure keeps its caption |
| `exhibit_citation` (Catalogue record) | `field_block_body` | Set like a museum label |
| `exhibit_audio` (Audio) | Title, `field_audio_file`, speaker/date, `field_audio_transcript` (required) | Oral history; custom player, transcript in a `<details>` |

Galleries also take `guide_text`, `guide_accordion` and `guide_video`.
`guide_text` with Style **Sources** gives each list item an id,
`#src-<surname>` (`src/ExhibitSources.php`), so citations can link to it.
Images use the `exhibit_640/1280/1920/2560` WebP styles as a srcset
(`_palefire_exhibit_image()`); the original is never sent. Alt text comes
from the media item. A figure with no image shows only to editors.

### Behaviour

- `js/exhibition.js`: one gallery shown, the rest `[hidden]`. TOC links,
  "Begin", the previous / next links it adds at each gallery's foot, and any
  `#link` in the text (an entry or source on another gallery) fade out,
  swap, fade in and glide to the target. Gallery changes push a history
  entry (Back works); jumps inside a gallery replace it. While a gallery is
  open the title page folds to a running head that keeps the `<h1>`.
- TOC: "Home" (the title page) first, entries listed under their gallery whatever the
  block's sub-link setting (`preprocessPalefireModularToc()`); no hide/show
  button. `modular-toc.js` picks its edge cases from the shown gallery.
- Cover opening (Opening "Cover", stored as `full`, the default with a
  lead): the lead full screen and fixed, as on a wall, with the number,
  heading, "In this gallery" links and the gallery's opening text (its
  untitled text blocks before anything else, moved there by
  `initCovers()`) over its lower left on the wash. The text is never
  folded: the title starts low on the first screen (`--pf-cover-top`), the
  lines at that screen's foot are faded, and the fade lifts over the first
  40% of a screen of scrolling (`measureCover()`, `--pf-fold`,
  `--pf-read`); below lg a veil comes in under the text as it does. The
  rest of the gallery follows below. Without the script the text stays in
  the body.
- Full screen, then pan (Opening "Full screen, then pan", stored as `pan`;
  also every gallery without a lead): from lg the text sits on the image's washed left
  half, as on the title page; below lg the image is on its own at the top,
  fading into the page, with the text under it. The heading is
  palefire_modular's, so `.pf-section__inner` is a grid that places the
  parts around it. The hero's stage is sticky: from lg the image stays
  pinned for `--pf-pan` of scrolling (set by the script from the image's
  proportions: how far it overflows the screen, between a third of a
  screen and 1.6 screens) while the heading and links fade, the wash
  clears and the image tours the whole of itself (`object-position` 0% 0%
  to 100% 100%, so a tall plate pans down and a wide photo across). Below
  lg, with reduced motion or without scroll-driven animations there is no
  pin (below lg the image pans as it scrolls off). After the pan the image
  stays put and the text and the previous / next links slide
  up over it on the page colour: the hero then spans the whole gallery
  (its sticky stage holds until the text covers it) and
  `.pf-section__inner::before` sizes the first rows instead.
- The pin runs in order: the heading and links fade (first 30% of a
  screen), the wash clears (15-40%), and only then does the image pan
  (from 40%, `--pf-fade-len`). The image starts right under the
  breadcrumb bar and pins there, so its top edge is never hidden. A
  full-screen lead's caption shows only in the lightbox (visually hidden
  under the image, where it labels the figure for screen readers).
- Breadcrumb: the landing's sub-header bar (`.pf-landing__bar`), with the
  TOC's hide/show button. While a gallery is open the title page folds to
  that bar, sticky under the header for the whole gallery; the `<h1>` stays
  for screen readers. `scroll-padding-top` allows for it. The trail follows
  the open gallery (Home / AVES / Preface; AVES then links back to the
  title page), and the page's crumb shows the full title on hover
  (`js/exhibition.js`, from the `<h1>`). At the bar's right end a zoom
  button (`.pf-exhibit-zoom`) opens the view's opening image (the cover, or
  the gallery's lead) in the lightbox; it fades in while that image is in
  sight and out once the text covers it or it scrolls away.
- The TOC's first link is "Home" (the title page). Galleries whose image
  pins and pans repeat their title, with the gallery number, at the start
  of the text (`.pf-exhibit-body-title`, aria-hidden; not below lg, where
  the heading is just above). "Skip to the text"
  glides past it; the stage allows for the running head
  (`--pf-exhibit-head-h`).
- Motion (`_exhibition.scss`): title page image, kicker, title, credit and
  prompt come in one after another; lead bands open with a curtain and
  pan from the image's top to its bottom over the first 70% of a screen of
  scrolling (`animation-timeline: scroll(root)`), full-width figures pan
  while they cross the screen (`view()`); "Show whole image" leads fade up
  uncropped; side figures rise in. All off under reduced motion, where a
  band rests on "Keep in view when cropped".
- **Wall** (Display "wall"; `_palefire_exhibit_wall()`, the gallery
  template, `js/exhibition.js`, `_exhibition.scss`): each entry's first
  image (or the first two of an image group) on a wall of justified rows,
  sized from the images' own proportions (`--pf-r`), so nothing shifts as
  they load; the wall takes the gallery's full width, its text keeps the
  measure. Labels: the entry's heading and "Pl. 40" (from "plate 40" in the
  caption) or the pair's captions ("Drawing · Proof"); a pair's second image
  crossfades in on hover. "Plate / A–Z" sorts when two or more captions give
  a plate (the tiles slide where the browser has view transitions). Hover
  lifts a tile and dims the rest; tiles rise in as they scroll into view
  (scroll-driven animations). The entries stay in the page, folded away, and
  are the object view's text; they are left out of the TOC
  (`data-toc-skip`: the wall is the gallery's index). Without JS the wall stays `[hidden]` and the gallery
  reads as a story. In place of a story's entry links, a line of facts from
  the wall (`.pf-exhibit-wall__stats`: "22 entries · Plates 2–154 · Sheet
  40 × 27 in", or "11 drawing–proof pairs"). A wall with a lead opens on it
  full screen (`.pf-exhibit-wall-hero`; with a caption it opens in the
  lightbox), with the number, title, facts and folded intro over its lower
  left on the page-colour wash (from the foot below lg, from the left from
  lg, the text kept in its dense part); "Read more" grows the area down,
  never the column wider, and the image and its wash stay on screen
  (`.pf-exhibit-wall-hero__frame`, sticky) while the opened text scrolls
  over them; below lg a veil of the page colour comes over the image while
  the intro is open. Folded, the intro shows four lines, or from lg over a
  lead as many lines as fill about 78% of the image's screen (measured,
  again on resize). No pan or band, whatever the Opening says.
  Without a lead, from lg the title and facts and the gallery's own text sit side by side as
  wall text, the wall full width below; text longer than four lines folds
  with "Read more" / "Show less" (`.pf-exhibit-wall__text`), so the first
  row of plates is on the first screen.
- **Object view** (a wall's tile, a link to its entry from the TOC or the
  text, the address `#anchor`, Back): a full-screen `<dialog>` in the page's
  reading colours. The image grows out of its tile (`view-transition-name:
  pf-object`) and shrinks back on Esc / Close, focus returning to the tile.
  It shows the tile's loaded image at once, then the size the stage needs;
  click to zoom where you click (largest size), move to look around. A pair
  is a comparison: the second image wiped over the first with a labelled
  slider; click it to zoom both together, like a single image. When the
  first has an alignment the frame takes the second's
  shape, the first is scaled, turned and moved onto it (the files are not
  changed), and Wipe / Fade switches the slider between a wipe and a
  cross-fade (eased; remembered while the page is open). Beside it (below on phones): count, heading, the caption, the
  entry's own text (ids stripped; its other images open in the lightbox),
  and "Actual size" when the gallery has a sheet size: every wall gallery's
  sheet drawn to scale, the current one outlined with the plate in it.
  Arrows, swipe and the buttons step through the wall in its current order,
  updating the address. Reduced motion: no grow, slide or rise.
- Long biographies fold to their opening lines with "Read the full
  biography". Lightbox: `<dialog>`, arrows, swipe, Esc, focus returns.
- Without JS every gallery shows in order and images open on their own.

### AVES

`scripts/data/aves.json` (made from the Wayback Machine's copy of
exhibitions.blogs.lib.lsu.edu/?p=5206 and its 53 "Read more" pop-ups) holds
the 19 galleries word for word: each pop-up's full text replaces its
shortened lead, the "Image:" and "Biography:" labels become the figure and
the biography, and old page links point at the new anchors. Images are the
4K originals; 17 exhibition images have no 4K file and are left for editors
(the script lists them). Unused 4K plates and photographs are the galleries'
decorative leads; change them in the editor.

### Occult Science and Philosophy in the Renaissance

`scripts/data/occult.json` holds the 2011 online exhibition (shown in Hill
Memorial Library, January to March 2010) from the Wayback Machine's copy of
exhibitions.blogs.lib.lsu.edu/?p=1257&all=1: Introduction, eight themed
galleries with 21 entries (catalogue record, images, text), Index, About. The
old Gallery page (thumbnails of the same images) is dropped. The students'
copy at lib.lsu.edu/occult was not used: its text has encoding errors and
its images are the same 1,000px files.

- **Text** word for word, except: "Amboise" to "Ambroise" Paré, missing
  spaces and line breaks in records and the Index, Felgenhauer filed under F
  in the Index, and "Main Library" for Middleton (as on AVES). Index lines
  link to their entries; Berthelot's drawings, which had no text, are the
  Ancient Origins lead.
- **New wording to check:** image captions, gallery lead captions, alt
  text, the credit line and the physical exhibition note.
- **To verify:** Defoe's record says Oxford: D. A. Talboys, 1840, but the
  scan (and its folder, BF 1601 D3 1728) is the London, 1728 title page.
- **Images** ("Occult Science 4K", 40 JPEGs, long edge at most 3,840px): the
  2011 files were 1,000px crops. Where the hard drive has a master scan
  (TIFFs, `old/extra scans`), the same framing was found in it and cut at
  full resolution (20 images); the rest are the 1,000px files. Additions
  from the drive: Bacon's portrait, Boyle's title page and five photographs
  of the installation (About, Science or Sorcery? lead). Each gallery's
  lead is one of its own images, credited; the cover is Francisci's
  sunflower plate, as on the 2011 home page.

### John James Audubon in Louisiana

`scripts/data/audubon.json` holds the 2021 digital exhibition (from
*Audubon at Oakley*, 2008): Audubon in Louisiana, Audubon at Oakley, Elephant
Folio (22 plates), Octavo Edition (17), Drawings and Proofs (37),
Manuscripts (3) and Suggested Reading. Each bird page of 2021 is an entry.

- **Text, word for word.** The landing and section pages come from the
  Wayback Machine (lib.lsu.edu/exhibitions/john-james-audubon). It never
  captured 78 of the 79 bird and manuscript pages, so those come from the
  students' copy (/audubon, nodes 437-520), whose text was mis-encoded four
  times over; the bytes survive as entities, so it decodes exactly (no word
  differs from the six pages the Wayback Machine has). Headings are the 2021
  menu names (the students' titles had "Boneparte", "Prarie", "Flamigo").
- **Corrected:** spacing and punctuation (spaces around "[now", "vol. 1",
  missing full stops and commas, " : " and " ;" in the readings, straight
  quotes and spaced hyphens set as curly quotes and dashes, "FIeld",
  "Sociey"); and, agreed with the editors, six Latin names (Psittacus,
  Sylvia childrenii, Sylvia roscoe, Haematopus palliatus, Ictinia
  mississippiensis, Picus querulus), "Susanne Low" and Heitman's pages
  44-53.
- **To verify:** three octavo citations give "Philadelphia: J. B.
  Chevalier, 1856" (Pine Creeping Wood-warbler, Wood Duck, Yellow-throated
  Vireo); the 1856 edition was not Chevalier's.
- **New wording to check:** gallery lead captions, alt text, credit line and
  physical exhibition note.
- **Images** ("Audubon 4K", 93 JPEGs, long edge at most 3,840px). 2021 showed
  600 × 400 close-ups; each was found in a full source on the drive (scored,
  and the low scores checked by eye) and the whole sheet is shown instead:
  the 39 plates from `Audubon Images for Heitman book/Edited images`, 46
  drawings and proofs and the Hurricane's first page at 1,000px or more
  (`source image for cropped ones`, `5188_001.jpg`). The Black-throated
  Waxwing drawing is the full sheet from the Louisiana Digital Library
  (lsu-sc-jjadp:13, a JPEG 2000 at 4,585 × 7,079, checked by eye against
  the 2021 crop; `palefire_exhibit_replace_image.php` for the built page).
  Each of the 11 drawings is aligned with its proof (`align` in the data):
  two to five matching points read off both (beak tips, wing tips, feet,
  title text), fitted, refined against the proof's lines and checked by
  eye; Western Shore Lark and Ivory Gull were redrawn for the plate, so
  theirs is the nearest fit. Three have no larger source and stay 2021 crops (Virginian Rail,
  Children's Warbler and Bear manuscripts). Leads: the Brown Pelican (folio,
  not in 2021) and full-resolution cuts of the 2021 close-ups of the Mocking
  Birds and Great Blue Heron; the cover is the Carolina Parrot. Unused:
  Alexander Wilson's Mocking Bird and Mississippi Kite plates.

## Reading colours and Settings

Settings > **Reading colours** sets page and text colours for the whole site.
Defined once in `$pf-reading-themes` (`_reading-themes.scss`); each swatch
writes only `--pf-g-*` tokens on `<html>`, and Bootstrap, the chrome, the
landing, the map and the calendar read those.

- **Twilight** is the default (`$pf-reading-default`, `DEFAULT_THEME`):
  charcoal `#272429`, text `#edebee`, headings and accent pale gold `#f2dc9b`,
  hovers to lilac `#d4c9dc` / cream gold `#fbf1d6`. `html.html.twig` puts it on
  `<html>` before any script runs; the default is never saved.
- **Others:** Material, Cocoa, Forest, Dusk, Night, Midnight, Solarized (dark),
  Cream, Soft cream, Lavender, Sepia, Sage (light). The first four show, the
  rest behind "More colours". Every swatch keeps text, muted text and links at
  4.5:1 or more, including on glass over a video.
- Optional swatch keys: `title`, `strong`, `em` (multicolour, as Material),
  `chrome`, `hover`, `link-hover`.
- **To add a swatch:** an entry in `$pf-reading-themes`, its key in
  `reading_themes` (`utility-panels.html.twig`), `THEMES` (`utility-panels.js`)
  and the pattern in `html.html.twig`; dark ones in `DARK_THEMES` too.

Other settings (saved in `localStorage` key `pf-a11y`, applied before first
paint by the inline script in `html.html.twig`):

| Group | Setting | `<html>` |
| --- | --- | --- |
| Text size | 100 / 115 / 130 / 150% | `data-pf-text-size` |
| Reading | Easy-to-read font (Atkinson Hyperlegible Next, loaded on demand) | `.pf-a11y-font` |
| Reading | Reader font for guides | `.pf-a11y-reader` |
| Reading | More text spacing (WCAG 1.4.12 values) | `.pf-a11y-spacing` |
| Reading | Underline all links | `.pf-a11y-underline` |
| Display | High contrast (pushes the swatch's colours apart, 8.3:1+) | `.pf-a11y-contrast` |
| Display | Reduce transparency | `.pf-a11y-solid` |
| Display | Strong focus outline | `.pf-a11y-focus` |
| Motion | Reduce motion (also zeroes animation delays) | `.pf-a11y-motion` |
| Motion | Pause background videos | `.pf-a11y-pausevideo` |

To add a setting: an item in `settings_groups` (`utility-panels.html.twig`),
its key in `SWITCHES` (`utility-panels.js`) and the inline script's list
(`html.html.twig`), and its CSS (`_a11y-settings.scss`). Scripts listen for
`palefire:a11ychange`.

## Header, menu and drawers

- **Bar** (Origami look, `$pf-header-*` in `_variables.scss`): logo | Primary
  menu | Utility icons; 87px expanded, 49px compact on scroll. The logo is
  inline SVG in `currentColor`, printed by `page.html.twig` when the Header
  region is empty.
- **Fonts:** Roboto (with a metric-matched local fallback, no CLS) and Handjet
  for the Hours clock, from Google Fonts, non-blocking.
- **Desktop dropdowns:** links fade in in sequence and their underline draws in
  and out (`$pf-dropdown-stagger`, `$pf-dropdown-fade`).
- **Below lg:** logo | Search | Menu. The menu is a full-screen offcanvas
  (`#pf-primary-menu`, the same element as the desktop menu) with quick-link
  tiles, text size and reading colours (copies of the Settings tiles,
  `initMenuSetting`), and sections as disclosure toggles. Quick-link drawers
  open over the menu (`openOverMenu`); × goes back to the menu.
- **Utility drawers** (`includes/utility-panels.html.twig`, outside the header
  and `#swup`): Bootstrap offcanvas from the left in the reading colours'
  glass (`$pf-panel-glass`).

| Icon | Panel | Contents |
| --- | --- | --- |
| `search` | Search | The landing search form |
| `clock` | Hours | Date, Handjet clock, OPEN/CLOSED per LibCal location (`hours_locations`), fetched on first open via `Drupal.palefireHours` |
| `chat-dots` | Ask Us | Three LibAnswers widgets as tabs, each iframe loaded when shown. Icon: FA duotone `comments-alt` |
| `person-circle` | Accounts | Account links (`accounts`) |
| `gear` | Settings | Above |

Menu links stay real `<a href>`s. Pick a panel with a `data-panel` link
attribute or by icon (`panel_map` in `menu--utility.html.twig`). To add a
panel: markup in `utility-panels.html.twig`, key in `panels`.

When logged in, the header sits under Drupal's admin toolbar (z-index 500).

## Page transitions (Swup 4)

`js/palefire-swup.js` swaps `#swup` (breadcrumb + body) and `#pf-primary-menu`
for anonymous visitors, re-running behaviours and syncing drupalSettings, meta
tags and body classes. Pages that need new assets or contain inline scripts get
a full load. Skipped: links with `data-no-swup`, `data-bs-toggle` or
`data-pf-over-menu`, and any click a script already handled
(`defaultPrevented`).

## Design conventions

- **Style:** sophisticated, modern museum feel with Swiss/International
  Typographic Style influence; smooth, subtle motion.
- **Lines:** every border and rule is the soft hairline `$pf-hairline` (20% of
  the section's line colour), unless a control's boundary needs 3:1 (WCAG
  1.4.11). High contrast sets `--pf-hairline-strength` to 100%.
- **Hover timing:** colour in over `$pf-hover-in` (.2s), out over
  `$pf-hover-out` (.5s), `ease`. Resting style carries the out transition,
  hover style the in.
- **Sequenced reveals:** `$pf-menu-stagger` (60ms) and `$pf-menu-fade` (.35s)
  for menus, accordions and the map legend.
- **H2 size:** `$pf-h2-size`, `clamp(1.5rem, 1rem + 1.2vw, 2.125rem)` for
  section titles (guides keep their own scale).
- **Motion off** under `prefers-reduced-motion` and Settings > Reduce motion,
  in CSS and in every script.
- **Icons:** inline SVG only (Bootstrap Icons, FA 5 Pro from `/libraries`),
  `aria-hidden="true"` when decorative.
- **Contrast:** 4.5:1 text, 3:1 large text and UI, checked on every reading
  colour and over a black or white video frame.

## Changelog

Newest first. One line per change; the commit has the details. Entries before
2026-10-21 are summarised by day: for the full write-ups, see this file in git
history (`git show fa05a94:README.md`).

### 2026-10-22

- **Hero: Sign in moves to the bar; the Quick Guide moves up.** "Sign in"
  (icon after the word, opens Aeon in a new tab) sits at the end of the
  sticky breadcrumb bar, the icon alone on phones; it leaves the Quick
  Guide, which keeps "See full guide". The Quick Guide follows At a glance
  closely (a third of the old space around its star rule, and no longer
  pushed to the panel's foot), so its heading shows in a laptop's first
  screen. Why: few visitors know how Special Collections works, so the
  guide is the next step after search.
- **Special Collections hero, third pass; TOC hidden on hero pages.** The
  search field is taller (4rem) with suggested searches under it, as in the
  mockup (Landing hero > Search suggestions, an optional field added by
  hand; built-in ones without it; one swipeable row on phones). The intro
  sits beside the title from xl, written for a one- or two-sentence intro.
  Pages that open with a Landing hero get `<html data-pf-hero>`
  (PalefireHooks::preprocessHtml(), copied on Swup visits), and the TOC
  starts hidden there, with its own saved choice
  (`palefire.tocCollapsed.hero`). The phone TOC button takes the TOC's
  glass and colours, so it follows the reading colours. Why: review on
  production.
- **Special Collections hero, second pass.** The full Quick Guide is back
  (lines of stops; the compact cards are gone), with the sign-in beside "See
  full guide". At a glance gains today's hours as its first cell (moved from
  beside the title), and every cell is now a label, one value line and one
  muted line, so the row reads as one even band; four across on wide
  panels, two by two beside the TOC. The Search button is filled with the
  swatch's accent, and from xl the title and intro sit side by side, as in
  the mockup. On view uses the Visit panel's column, padding and corners.
  Why: review on production; the cards, the uneven cells and On view's own
  width looked off.
- **From the stacks: a featured item, the next events and the newest blog
  posts.** A new section paragraph (`landing_stacks`, created by
  `scripts/palefire_landing_stacks_setup.php`) with `js/upcoming.js` for the
  events. The hero's Next event reads its calendar too, and the blog lookup
  is now one helper (`_palefire_blog_posts()`) for both. Why: Special
  Collections has a handful of events a month, so a day-by-day carousel was
  mostly empty; three upcoming events and three posts beside one featured
  object keep the homepage current without the clutter.
- **Family tree links: one row each, icon in a box.** Both lists (Family
  trees and the parish panels) now share one link
  (includes/family-tree-link.html.twig). The whole row is the link: the
  icon in a 32px hairline box, then the title in the text colour with the
  place under it. Hover and focus turn the title and the box gold, with
  no underline, since the row opens the viewer, not a page.
  - **Alignment:** the box's top sits on the top of the title's capitals.
    CSS `text-box` trims the title to its capitals and the place to its
    baseline; older browsers get a margin fallback.
  - **Spacing:** the space beside the box equals the space above and
    below it (16px, `--pf-tree-pad`).
  - **Line spacing:** titles are at 1.25 with the place 5px under, so a
    wrapped title no longer reads as two items.
  - **Viewer title:** the viewer takes the tree's title from the title
    line, not the whole row.
- **Special Collections hero: calmer first screen, exhibitions after the
  map.** The lead panel is now title, hours, intro, search, an At a glance
  row (next event, on view, latest blog post) and the Quick Guide as three
  compact A/B/C cards, with the sign-in and full guide links beside its
  heading. The exhibition tiles moved under the map as On view, on the same
  glass. The Next event chip, the blog row and the tiles in the lead are
  gone, along with their CSS. Why: the first screen had six competing
  parts; research access now leads, and visitors see where the galleries
  are before what's in them. The full guide page keeps its lines of stops.
- **Tile showcase and hero tiles take Tiles or Modular pages.** One
  helper, `_palefire_node_card()`, reads either into the same card
  (title, link, Category label, intro note, cover). A Tile's cover is its
  Tile cover; a Modular page's is its Image cover, else its landing's
  poster. Allow the other type in each field's Reference type (Manage
  fields) to use it. Why: the homepage's sections mix both kinds of
  content.
- **Tile showcase: the `index` layout.** A numbered list with no pictures:
  number, title and note, label, and an arrow that slides on hover, between
  soft hairlines; the label drops under the note on narrow screens. For
  Collections & services on the Special Collections homepage, replacing
  two image-tile sections. Add `index` to the Layout field's allowed
  values (Manage fields) to use it.
- **Viewer: the panel's bar at the top.** The pinned row (count or
  "Family tree", and Close) started 3rem down and only reached the top
  on scroll. It now starts at the panel's top edge, with Close level with
  the stage's toolbar, so the controls read as one bar.
- **Family trees zoom like images.** The whole tree is fitted to the
  stage. A click or Zoom goes 2.5× there; the mouse looks around and a
  finger drags (the viewer's own zoom, the SVG as `.pf-viewer__zoomable`).
  This replaces the Fit / Actual size switch and its scrolling pane.
- **Family trees: the duotone project-diagram icon.** It appears before
  each tree link (Family trees list and parish panels) and before "Family
  tree" in the viewer. `_palefire_fa_symbol()` reads it from the server's
  Font Awesome Pro files (never in this repo) and prints it once per block
  as an SVG `<symbol>`; the links `<use>` it, so the hundreds of rows add
  little to the page. It takes the accent colour, and its light layer
  stays at 40%. If the file is missing, no icon shows.
- **Family trees: Copy link and Original page as buttons.** Before they
  were bare text with no alignment. They're now a pair of hairline-framed
  buttons like the viewer's Close: an icon and a mono label each, 44px
  tall.
- **One full-screen viewer for exhibitions and the newspaper family
  trees.** The exhibitions' object view is now its own library,
  `palefire/viewer` (js/viewer.js, scss/components/_viewer.scss, classes
  `pf-viewer__*`). It covers the stage with Zoom, the text fold (I) and
  Close; the arrows, keys and swipe; the pinned glass row and the panel;
  click and drag zoom; focus; and the view-transition morph. Pages drive
  it with a handler (`step`, `close`, `closed`, optional `zoom`) and fill
  its parts. Only the libraries that need it depend on it.
  - **Exhibitions** keep their own parts (comparison, Actual size,
    pictures, address) and look and behave as before.
  - **The family tree** (js/family-tree-viewer.js) now opens in it. The
    drawing is on the stage; the title, years, Copy link, Original page,
    Key and notes are in the panel. Zoom is "Actual size" for a tree
    wider than the stage, which a mouse can drag around. `?tree=`, Back
    and the fallback to the page are unchanged. On phones the drawing
    takes the stage's full width (no arrows to leave room for).
  - **Fix:** a viewer closed inside a skipped view transition didn't
    always fire "close" in Chrome, which left the page locked and focus
    lost. The viewer now finishes closing itself.
  - Opened by a different kind of page (Swup keeps it between pages),
    it starts fresh, without the last page's parts.
- **Focal Point preview shows the cover's wash.**
  images/focal-preview/wash-desktop.png (1600 × 1000) and wash-phone.png
  (900 × 1600) are the title page's wash, drawn by
  build/focal-preview-washes.js from the same eased gradient as
  `pf-eased-wash()` in the default swatch's page colour. In the "Cover
  preview" image styles, an Image Effects Watermark lays them over the
  focal point crop, so editors see where the title and text will cover
  the image. Rerun the script if the wash changes.
- **Exhibition covers follow the image's focal point.** Editors set the
  point on the Media image (Focal Point module, Image (Focal Point)
  widget). `_palefire_focal_point()` turns it into an `object-position`
  ("62.5% 30%"), so that spot stays in view at every screen shape,
  without Focal Point's fixed-crop image styles. It's used by the title
  page cover (in place of the CSS 70% center), and by gallery and wall
  leads (in place of "Keep in view when cropped", which still applies
  when an image has no point). The compact band's pan starts at the
  point's horizontal position. With the module off or no point set,
  nothing changes.
- **Wall galleries: a shorter intro before Read more.** The fold showed
  text up to 78% of the lead's height (about ten lines at 1440 × 900).
  The stats, text and Read more now take the lower third (`introLimit`,
  js/exhibition.js), at least four lines, so the heading sits lower and
  more of the image shows.
- **Object view on phones: Close by the other controls.** Below lg,
  Close is in the stage's top-right toolbar, beside Zoom and the fold,
  instead of in the panel. Focus on opening goes to whichever Close
  shows.
- **Object view: the pinned row is glass.** The count and Close row uses
  the breadcrumb bar's glass (`--pf-sidebar-bg`, `--pf-glass-filter`), so
  scrolling text blurs away under it instead of being cut off. It's solid
  where transparency is reduced.
- **Object view: Close stays in sight.** The panel's top row (the count
  and Close) is sticky. It stays at the panel's top while the text
  scrolls, edge to edge on the page colour, and from lg it lines up with
  the stage's Zoom and fold buttons. A hairline fades in under it once
  the text starts passing beneath (scroll-driven; with no support, no
  line). The panel's padding is in custom properties so the row can
  reach across it.
- **Outside links: the arrow after the link's real last word.** It went
  after the last text directly inside the link, so in "octavo editions
  of *Birds of America*" it landed after "of". A title typed with a
  trailing space also left an underlined gap before it. The last word is
  now found inside any `<em>` or `<strong>`, and spaces after it are
  dropped.
- **Actual size: the person for scale is removed again.** It looked out
  of place beside the sheets. The diagram is back to 9rem, as before it.
- **Field guide rows: names from image groups too.** A drawing and its
  proof are an image group with one caption, which the rows didn't read,
  so the plover's row had no scientific name. The caption's spelling now
  comes before the entry title (Black-bellied Plover, Yellow-billed
  Cuckoo).
- **Exhibitions: outside links open in a new tab.** Every link to
  another site in an exhibition's text and captions opens a new tab, so
  the exhibition (the gallery, an open image) stays put. Each says so: an
  arrow kept on its last word's line, and "(opens in a new tab)" for
  screen readers (`markOutside`, js/exhibition.js).
- **Exhibitions: Audubon field guide links as ruled rows.** "View bird in
  National Audubon Society Guide…" becomes a row with a kicker, the
  bird's modern common name, its scientific name and an arrow
  (src/ExhibitFieldGuide.php, a #post_render on guide_text). The common
  name comes from the entry title or a caption phrase that matches the
  link's address, else from the address with its hyphens and possessives
  restored. All 72 Audubon links were checked. The scientific name is the
  caption's "[now …]" name. The row is ruled above only, so it never
  doubles the rule over Actual size.
- **Actual size: the sheet's outline is the paper's edge.** The current
  sheet's gold border read as part of its size. Every sheet now has the
  same hairline, the plate fills its own sheet edge to edge, and "This
  plate" under its label says which one it is.
- **One image viewer for exhibitions: the object view.** The lightbox is
  gone (its JS and CSS). Images in the text, a gallery's lead and the
  bar's zoom button now open in the wall's object view: the image on the
  stage, its caption and "Image 2 of 4" in the panel, stepping through
  the gallery's images. Opened from an entry's text, Close goes back to
  the entry, and an image with no caption has no panel. The image grows
  out of its place on the page where the browser has view transitions.
  The stage has a toolbar:
  - **Zoom**, the same zoom as a click, and reachable by keyboard.
  - **Fold the text away** (or press I). The panel slides off and the
    image takes the screen. The choice is kept per browser
    (localStorage), and Close moves onto the stage while the panel is
    folded.
  - **Drag to pan on touch.** Zoomed images could only be panned with a
    mouse before.
- **Title page: no line of image at the cover's foot, at any zoom.** The
  1px fix below didn't hold at 125%. The clip cut the image and its wash
  at one edge, and the anti-aliased rows of the two layers let a broken
  bright line through. The wash is now the head's own `::after`, which
  the clip doesn't cut. The image stops .25rem above the foot, under the
  wash's solid edge.
- **Title page: the cover fades out at the head's foot.** Under the Begin
  button the image stopped at a hard edge against the footer. A row of
  bare image also showed through under the wash there: the wash and the
  clip ended on the same edge, and rounding opened a 1px gap. From lg the
  image now fades into the page colour over the foot (4–8rem), and the
  wash runs 1px past the clip.
- **Title page: the cover image holds still while the page scrolls.**
  It scrolled away with the head, footer and all. It is now sticky under
  the bar inside a media box that runs a screen past the head. clip-path
  shows it only within the head, so the head's foot (and then the footer)
  slides over a still image. It's set back by the header's shrink, as on a
  wall's lead, so it doesn't move or re-crop as the header compacts. The
  head is `overflow: clip`, so the page is no longer than before. The
  zoom button stops counting the image once the head's foot passes it.
- **Wall: no flicker when moving between plates.** The other plates dim
  only while a plate is under the pointer, so crossing the gap between two
  plates undimmed the whole wall for a moment. Dimming and highlighting are
  still immediate, but going back to full opacity now waits 0.2s, which is
  longer than a quick move across a gap. With reduced motion there is no
  fade, just the pause.
- **Footer: Special Collections version on /v2/exhibitions too.** The
  exhibitions page and the pages under it are Special Collections content,
  so they get its footer. More paths go in
  `PalefireHooks::SPECIAL_EXTRA`.
- **Footer: Special Collections version shows on /v2/special.** The check
  looked for `/special`, but on lib.lsu.edu "v2" is part of the alias (the
  base path is `/`), so every page got the Main Library footer and the
  footer's Special Collections links missed "v2". Both now use
  `PalefireHooks::SPECIAL_ROOT` (`/v2/special`); change it there when the
  pages move.
- **Footer: Special Collections or Main Library.** The footer held only the
  logo. It now leads with the section's own building: on `/special` and the
  pages under it, Hill Memorial Library (Special Collections) with "Plan a
  research visit"; everywhere else, the Main Library with "Ask a
  librarian". Each shows today's hours, phone and Ask Us, names the other
  building in one line with its hours, and has two link lists, LSU legal
  links and that section's Instagram and Facebook. Content lives in
  `includes/footer.html.twig`; `footer_special` comes from
  `PalefireHooks::preprocessPage()`. The footer is now a Swup container
  (`#pf-footer`), so it changes with the section, and `js/footer.js` asks
  LibCal for its hours only when it nears the viewport. It is the last
  snap point on scroll-snap pages. Twitter links were dropped (FA 5 has no
  X icon).
- **Drawing/proof comparison shows on iOS Safari.** The frame was sized by
  `aspect-ratio` alone, and iOS Safari gives such a box (all of its children
  absolutely placed) no width, so nothing showed. It now sits in a
  `.pf-exhibit-compare__fit` size container and takes the largest size that
  fits at the images' proportions, with width and height both set.
- **Actual size: each label under its sheet.** The sheets and their labels
  were two separate rows that didn't line up. Each sheet is now a list item
  with its name and size under it, and all the sheets stand on one baseline.
  Sizes come from `--pf-w`/`--pf-h` against `--pf-sheet-max`, not
  `aspect-ratio`.

### 2026-10-21

- **Faster Largest Contentful Paint (LCP).** Chrome never counts content first painted at opacity 0,
  and the page fade-in (`.pf-fade body`) and the exhibition's load fades
  (`$pf-exhibit-fade-from`) all started there, so the cover and title went
  unmeasured and LCP fell on a TOC link repainted after the reveal. They
  now start at 0.01, which looks the same. On an address for a gallery
  (`#…`) its opening image is made eager and high priority as soon as
  `exhibition.js` starts, and the title page's cover, now lazy in the
  markup, isn't fetched at all until the title page is opened; without a
  `#`, a script in `<head>` (`PalefireHooks::exhibitHead()`) preloads it
  as early as before. Measured on AVES: the title page's LCP is now its
  cover at about 0.95s (was a TOC link); a gallery link no longer
  downloads the 270KB title cover. Why: Lighthouse's 2.1s LCP.
- **Exhibitions: 17px reading text.** Text blocks, biographies and
  transcripts go from 16px to 17px (1.0625rem, so Settings > Text size
  still scales it), with the 1.7 line height and 1.5em paragraph spacing
  following; captions, labels and records stay as they were. Why: about
  75 characters a line at the full measure instead of about 85, easier for
  long reading.
- **Exhibitions: a cover's text on its first screen; no "Skip" without a
  lead.** A cover's area is the first screen under the full header again
  (`--pf-header-full`, held while compact); only its image keeps the
  compact-header size (`--pf-cover-img-h`) so it stays still. A cover with
  no links or opening text keeps a margin under its title. Galleries
  without a lead lose "Skip to the text", and long links in exhibition text
  break instead of widening a phone's page. Why: Modern Fieldwork's title
  sat under the screen's foot, the prompt had nothing to skip, and Sources
  scrolled sideways at 320px.
- **Exhibitions: looser reading text.** Text blocks, biographies and
  transcripts (also in the object view) get line height 1.7 (was 1.5) and
  1.5em between paragraphs (was 1em); captions, labels and records keep
  theirs, and guides and other pages are unchanged. Why: exhibitions are
  read at length.
- **TOC: two-line items read as one.** Section links set their lines at
  1.3 with 0.4rem above and below (still at least 44px), sub-links at 1.35
  with a little more padding, so the gap between items is clearly larger
  than the gap between an item's lines, on every page with the TOC. Why:
  two-line items ran into their neighbours.
- **Exhibitions: a cover's image never moves, and "Show more".** The
  cover's frame is pinned under the bar from the first scroll and follows
  it as the header shrinks, while the image and washes inside are set back
  by the shrink so far (`--pf-cover-shift`): on screen they stay put and
  the shrink only uncovers the top strip. "In this gallery" prints every
  entry; past the sixth they fold behind a "Show @count more" / "Show
  less" button set like "Read more" (`initEntryLinks()`), in place of the
  boxed "+ n more" summary. Why: the image still rose with the first
  scroll, and the summary looked out of place.
- **Exhibitions: a cover holds still while the header shrinks.** A wall's
  or story's cover image is now sized and pinned from the compact header's
  height (`$pf-header-bar-compact-h`) instead of the live `--pf-header-h`,
  so the shrink no longer moves or re-crops it, or moves the text over it;
  at rest the full header covers its top strip. Its image also fills the
  frame again (`.pf-section img` had set `height: auto`, leaving wide images
  short of the foot). Why: the shift on scroll was disorienting.
- **Exhibitions: a cover's text without "Read more".** A story cover now
  shows all its opening text; the title starts low on the first screen,
  the lines at its foot are faded, and the fade lifts as you scroll (with
  a veil under the text on phones). Walls keep "Read more". Why: opening
  the folded text made it jump up the screen.
- **Exhibitions: story galleries open on a fixed cover.** A story gallery
  with a lead now opens like a wall: the image full screen and fixed, with
  the title, the "In this gallery" links and the opening text on its wash
  ("Read more" for longer text), the entries below. The Opening field's
  "Full screen" is now "Cover" (same stored value, so every such gallery
  switches); the new "Full screen, then pan" keeps the earlier opening,
  and "Compact" stays (re-run `palefire_exhibit_setup.php` for the new
  option and labels). Why: the cover reads better than the pan, which hid
  the text behind a long scroll.
- **Exhibitions: zoom on a drawing and its proof.** Clicking a comparison
  zooms both images and the cut together where clicked, at their largest
  size, as a single image does. Why: the pairs are the ones most worth
  looking at closely.
- **Exhibitions: drawings lined up with their proofs.** New Image field
  `field_figure_align` (setup script) places a drawing over its proof in a
  wall's comparison: scaled, turned and moved, the files untouched. The
  comparison then takes the proof's shape and gets Wipe / Fade. Audubon's 11
  pairs are aligned (`audubon.json`, import and wall scripts). Why: the
  drawings sat at other scales and angles, so the slider compared sheets,
  not birds.
- **Audubon: the whole Waxwing drawing.** The Black-throated Waxwing
  drawing was the 2021 site's 600 × 400 close-up (the drive has no full
  scan); it is now the full sheet from the Louisiana Digital Library, at
  2,487 × 3,840. New `palefire_exhibit_replace_image.php` swaps it in on the
  built page. Why: next to its full proof the drawing looked cut off.
- **Exhibitions: wall entries out of the TOC.** A wall gallery's entries
  are no longer TOC sub-links (`data-toc-skip`); the wall is the gallery's
  index, and links to its entries still open the object view. Audubon's TOC
  goes from 79 sub-links to the 3 manuscripts. Why: with three walls of 17
  to 37 entries the TOC had become a long scroll.
- **Exhibitions: a wall's image stays put under its text.** The lead (and
  its wash) is pinned while the opened intro scrolls over it, with a veil
  of the page colour below lg; from lg the folded intro shows as many lines
  as fill the image (14 at 1440 × 900, 7 at 1024 × 768) instead of four.
  Why: the opened text scrolled the image away, and the folded one left the
  screen half empty on desktop.
- **Exhibitions: a wall's text on its image, and its facts.** A wall
  gallery's number, title, facts and intro now sit over its full-screen
  lead's lower left, on the left-to-right wash (from the foot on phones);
  "Read more" opens the intro downwards within the wash, the image growing
  with it. The falling title is gone. A new line of facts replaces a
  story's entry links: entries, plate range, compared pairs, sheet size
  (`_palefire_exhibit_wall()` `stats`). Why: the text belongs on the
  showcase image, and a wall's index is the wall itself.
- **Exhibitions: a wall's title falls into place.** A wall gallery with a
  lead opens on it full screen again, the title over its foot; scrolling,
  the title holds while the image slides away, and settles beside the
  intro. Without a lead the wall text opens the gallery as before. Why: a
  showcase entrance without the long pan before the plates.
- **Exhibitions: eased washes.** The title page's and the galleries'
  page-colour washes over their images fade along an ease-in-out curve in
  12 stops (`pf-eased-wash()`), instead of two straight segments whose
  bends showed as steps. Text keeps its contrast (the wash is as dense as
  before where the text sits).
- **Breadcrumb on one line.** The modular breadcrumb (and the landing bar's)
  never wraps: items that don't fit end in an ellipsis, the middle ones
  first, then the current page; Home stays whole. Below md it is 11px with
  tighter tracking and separators. The exhibition's gallery crumb is in a
  `<span>` like the others, so it can be cut too. Why: on phones the
  breadcrumb ran to three lines.
- **Exhibitions: wall galleries open on the wall.** No full-screen opening
  or pan on a wall gallery; from lg the title and the intro sit side by side,
  the intro folded to four lines with "Read more". Why: scrolling past a
  pinned image and several paragraphs before the plates was the wrong way
  round for a gallery view; the first row of plates now shows on the first
  screen at 1440 × 900 and 1024 × 768.
- **Exhibitions: object view fits tall images.** The image is centred in
  its frame by absolute placement, so its max height follows the frame and
  a portrait plate shows whole instead of filling the width and being
  cropped. The side panel narrows to 20rem (at most 30rem or 32vw) and the
  stage's padding drops a little, so at 1024px the image has 568px instead
  of 384px. Why: the Prothonotary Warbler and other upright plates showed
  only their top half.
- **Exhibitions: wall galleries.** A gallery's new Display option (re-run
  `palefire_exhibit_setup.php`) shows its entries as a wall of whole images
  that open full screen in an object view, with sorting, a drawing-to-proof
  slider and "Actual size" (Sheet size). Audubon's Elephant Folio, Octavo
  Edition and Drawings and Proofs use it (`data/audubon.json`;
  `palefire_exhibit_audubon_wall.php` for the page already built). Why:
  galleries of 17 to 37 plates read better side by side than one by one.
- **Exhibitions:** John James Audubon in Louisiana
  (`palefire_exhibit_audubon.php`, `data/audubon.json`): 79 entries in five
  galleries plus two opening galleries and readings, as an unpublished
  exhibition, with the whole plate or sheet in place of 2021's close-ups.
  Why: the third online exhibition to move into exhibition mode; the
  students' copy at /audubon has garbled text and 600px images.
- **Exhibitions:** the title page's `<h1>` sizes itself by its length.
  `exhibit-cover.html.twig` passes the character count and longest word
  (`--pf-title-chars`, `--pf-title-word`); the size is the smallest of the
  usual display size, about three lines of the text column (a container),
  and the longest word on one line. "AVES" is unchanged; Occult Science
  sets in three lines (76px wide, 35px at 320px). Why: the 48-character
  title ran to five lines at 136px and pushed past its column.
- **Exhibitions:** Occult Science and Philosophy in the Renaissance
  (`palefire_exhibit_occult.php`, `data/occult.json`), built as an
  unpublished exhibition from the 2011 online exhibition's text and the
  hard drive's scans, 20 of them recut at full resolution. Why: the second
  Hill Online Exhibition to move into exhibition mode; the students' copy
  at /occult has broken text and 1,000px images.
- **Exhibitions:** the physical exhibition tile takes a short note instead
  of a checkbox (`field_exhibit_physical_note`; re-run
  `palefire_exhibit_setup.php`), shown in a tooltip on hover, focus or tap.
  The checkbox `field_exhibit_physical` is no longer used: delete it in
  Manage fields if it was created. Why: the tile can now say when and where
  the exhibition was.
- **Exhibitions:** optional year and "accompanied a physical exhibition"
  tiles on the title page, under the credit (re-run
  `palefire_exhibit_setup.php`, then fill them on the page). AVES typos
  fixed in `data/aves.json`, with `palefire_exhibit_aves_typos.php` for the
  live page: names such as "John Gould 1804-1881)", Vieillot's dates
  (copied from Markgraf), spacing around hyphens and dashes, "from from".
  Why: the title page didn't say when the exhibition was or that it was on
  display, and the typos showed in the TOC.
- **Exhibitions:** a full-screen gallery image now starts right under the
  breadcrumb bar instead of behind it; its caption shows only in the
  lightbox (still read as the figure's caption); the title page's cover
  drops the dark band at its top; below lg the text no longer repeats the
  gallery title. Why: the image's top was always hidden, and the caption
  and title read as repeats.
- **Exhibitions:** a zoom button at the right end of the breadcrumb bar
  opens the cover or the gallery's lead image full screen, and fades away
  once the image is out of sight. It replaces the full opening's corner
  mark, which sat under the header. Why: the cover had no visible way into
  the lightbox.
- **Exhibitions:** gallery URLs back to `#anchor` (the per-gallery paths
  didn't open their gallery; `palefire_exhibit_paths.php` removed). The
  breadcrumb follows the open gallery and shows the full title on hover;
  the TOC's "Title page" is now "Home"; the gallery's title opens its text
  again after the image. Why: the text began as a bare paragraph, and the
  trail didn't say where you were.
- **Exhibition galleries:** the first scroll fades the heading, links and
  wash; only then does the image pan. The image runs up under the frosted
  header and a landing-style breadcrumb bar (with the TOC's hide/show
  button, now on exhibitions too), which stays as the running head.
  Why: the cover moved as soon as you scrolled, and the header hid the
  image.
- **Exhibition: the text slides over the image.** After the pan, the
  gallery's image stays where it is and the caption and text move up over
  it on the page colour, so the text moves and the image stays put.
- **AVES content:** the nine names written in capitals now in Capital
  Case, and entry headings no longer set in capitals; "Middleton Library"
  is now "Main Library" (14 records); 16 of the 17 missing images, from the
  catalogue's TIFFs (converted to JPEG in `Section 4 (additions)`); top
  image captions for 11 galleries from the print catalogue's illustration
  lists. `palefire_exhibit_aves_update.php` applies it to the live page.
- **Exhibition galleries open like the title page:** the lead fills the
  screen with the gallery number, title and links to its entries; scrolling,
  the image stays pinned while they fade, then pans through the whole of
  itself before its credit and the text (one image, no second copy). Per-gallery Opening (`field_exhibit_opener`, re-run
  `palefire_exhibit_setup.php`): full screen (default, also for existing
  galleries) or compact (the band). Links inside a gallery now glide. Why:
  a showcase entrance to each gallery and a way to see each plate whole.
- **Exhibition lead captions:** new "Lead image caption" field
  (`field_exhibit_lead_caption`, re-run `palefire_exhibit_setup.php`). With
  one, the lead is announced, opens in the lightbox and is credited under
  the band; without, it stays decorative. Drafts for the 9 AVES leads that
  come from books the exhibition describes, each with a "[Editor: please
  verify …]" line (`palefire_exhibit_aves_lead_captions.php`); the other 7
  need a source. Why: the leads are collection objects and were uncredited.
- **Exhibition leads:** the band is taller (70% of the screen) and pans
  from the image's top to its bottom as the page scrolls, instead of a 7%
  drift; new per-gallery "Show whole image" checkbox
  (`field_exhibit_lead_whole`, re-run `palefire_exhibit_setup.php`) shows the
  plate uncropped and centred. Why: the band showed only one patch of most
  plates (the Lear macaw on Introduction was all feathers).
- **Exhibition pages** (new Type, `exhibition`): a title page whose image,
  title and prompt come in in sequence, then one gallery at a time with
  cross-fades driven by the TOC; galleries hold entries (a second nesting
  level), images with captions and a lightbox, image groups, catalogue
  records and oral history audio with transcripts, all as WYSIWYG, media and
  list fields. Leads open with a curtain and drift with the scroll. AVES
  (2012) rebuilt from the Wayback Machine with its 4K images
  (`palefire_exhibit_aves.php`). Why: a showcase format for online
  exhibitions, replacing the old WordPress page-by-page site.
- **Request paths: screenshots on demand.** Steps A1–A3 and B1–B4 open a
  "how" panel with screenshots of the catalog and request system, and the fees
  and timing (50 cents a page plus $10 per order; invoice in 24–48 hours;
  5–7 business days). The guide's script has the same facts in its steps. From
  the unpublished LibGuide, trimmed: the old Special Collections home page and
  the staff-only "unlimited requests" banner are left out. Why: show what each
  screen looks like without turning the guide into a nine-step LibGuide.
- **Parish map:** descriptions under linked entries muted and smaller, so the
  titles lead; more room above the sticky map (2–5rem by window height, was
  1.5rem), and it shrinks to fit short windows. Why: entries ran together, and
  the map sat tight against the header on large screens.
- **README reorganised.** Rewritten as a reference by topic with a short
  changelog; superseded iterations and test measurements removed. Why: the file
  had grown to about 5,000 lines of mostly outdated entries.
- **Guide editors:** optional `field_guide_editors` shown as "Edited by". Why:
  guides are often kept up by more than one person.
- **Parish map:** list and map light each other on hover; hatch at rest 30% →
  10%.
- **Family tree viewer:** trees open in a full-screen Palefire viewer;
  shareable `?tree=` links, Back closes it, themed scrollbars.
- **Fix:** Louisiana Newspapers guide gave a blank 500; family trees now read
  with one query and fail soft.
- **Louisiana Newspapers guide** with a clickable parish map.
- **Quick Guide:** editable title/intro/link fields; options A, B, C in
  boxes with FA light arrows; fewer "or" rules, labels centred.
- **Guide images** shrink to their column (no sideways scroll at 320px).

### 2026-10-20

- Quick Guide added under the hero's features; TOC Home gets "Landing" and
  "Quick guide" sub-links; light swatches fixed on landings.
- Landing snap works with a lead taller than the window.
- Special Collections guide: decision tree first, then request paths as
  subway lines (`[request-paths]`); guide text moves to WYSIWYG.
- Hero: many iterations of the row under the tiles, ending with the Next
  event line, blog post with a divider and the Aeon sign-in link.
- Hero tiles from Modular pages (Image Cover first), several blog categories.
- New Landing department section; landing-only pages get the TOC; TOC open by
  default.
- Section snap works on tall sections (proximity marker); snapping md and up
  only.
- Map held perfectly still through the reveal (`position: sticky`); more room
  under the Visit panel; landing bar sticky through Home and Map.

### 2026-10-19

- Curtain reveal for the map; one eased glide (`Drupal.palefireGlide`) for
  the landing snap and every TOC jump; TOC jumps land every section alike and
  add no history entries.
- Section headings slide in; events carousel reveals in sequence; one H2 size;
  event image cards.
- Landing hero: full-screen lead, three opening tiles, sticky bar and video
  button, open/closed hours line; map legend inside the Visit panel; map drawn
  once; quieter hatching on desktop; hairline legend.
- Header sits under the admin toolbar.

### 2026-10-18

- "Bento" renamed to "landing" in the theme.
- Twilight refined: charcoal, one pale gold.
- Landing hero lead (blog post, exhibition tiles, Next event); lead fills the
  first screen and snaps to the map; sub-header bar; video layer fixed to the
  viewport.
- Building map: Intro is the summary, building description above the legend;
  phone sizing.
- Tile showcase and its scroll reveal; soft hairlines replace solid lines;
  TOC hide/show button restored.

### 2026-10-17

- Twilight becomes the default reading colour; one hover timing everywhere.
- Mobile header and menu redesigned as a full-screen drawer with toggles,
  quick links, text size and reading colours; drawers open over it.
- Landing hero paragraph; Next event card.
- Cache busting fixed; guide accordions and dropdowns animate calmly; TOC
  activation at three-quarters down.

### 2026-10-16

- Guide pages (content model, scripts, guide theme); reading colours
  everywhere; drawers in the guides' style; cache busting.
- Landing layout: lead panel, Visit strip, map-only option, Swiss layer,
  "Light, dark at the map"; events carousel Swiss styling.
