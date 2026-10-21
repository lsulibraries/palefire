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
| `palefire_guide_setup.php` | Guide content model: Resource type, guide paragraph types, guide fields (incl. Guide editors) |
| `palefire_louisiana_newspapers_setup.php` | `parish_map`, `parish_entry`, `family_trees` paragraph types |
| `palefire_guide_wysiwyg.php` | Moves existing guides' text to the WYSIWYG format (dry run unless `-- apply=1`) |
| `palefire_exhibit_setup.php` | Exhibition content model: `exhibition` mode, title page fields, `exhibit_*` paragraph types, `exhibit_*` image styles, the Sources text style (after the guide setup) |
| `palefire_exhibit_aves.php` | Builds AVES as an unpublished exhibition (`-- images="<AVES exhibition 4K folder>" path=/v2/exhibitions/aves`), importing the 4K images into Media |
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
  in `<head>` so there is no flash. Every page starts shown; a visitor's choice
  is saved per page type (`palefire.tocCollapsed.landing` /
  `palefire.tocCollapsed`).
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

- **Bar:** breadcrumb and the background-video button, sticky under the header
  for the whole landing.
- **Lead panel** (full screen from md): title, "Open now" hours line, intro,
  Next event line (`js/hero-event.js`, reads the events carousel's calendars
  and campuses), the tile accordion (`includes/landing-exhibits.html.twig`;
  `js/exhibit-rotate.js` crossfades multi-image tiles with a pause button),
  the blog post and "Sign in to request materials" (Aeon), then the Quick
  Guide.
- **Quick Guide:** the request paths from
  `includes/guide-request-paths.html.twig`, shared with the full guide: three
  options A, B, C, each a line of stops. Edit the stops there and both pages
  change.
- **Map and Visit strip:** the building map with its key and the Visit strip
  (`includes/visit-strip.html.twig`) in one glass panel.

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
fields plus `field_tile_layout` (`stagger` / `grid`) and `field_tiles` (Tile
nodes). Each card: the Tile's title (h3), its Tile Cover through the
`tile_showcase_portrait`/`_landscape` responsive styles, its Category terms as
the label, and its link. Layouts use container queries; the staggered row is a
swipeable strip under 60rem. `js/tile-reveal.js` animates cards in on scroll
(nothing hidden without JS or with reduced motion). A scrim keeps white titles
at 4.5:1 on any image.

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
to begin."), and `field_intro` if filled.

| Paragraph type | Fields | Notes |
| --- | --- | --- |
| `exhibit_page` (Gallery) | Title, anchor, TOC hide, `field_exhibit_lead` + `_lead_focus` + `_lead_whole`, `field_guide_blocks` | A section. The lead is decorative: a full-width band (curtain, then a scroll-pan) or, with "Show whole image", the plate uncropped and centred |
| `exhibit_entry` (Entry) | `field_block_title` (h3, TOC sub-link), `field_section_anchor`, `field_entry_bio`, `field_guide_blocks` | A person or book; the second level of nesting under the TOC |
| `exhibit_figure` (Image) | `field_figure_image`, `field_figure_caption`, `field_figure_layout` | Layouts: beside the text (image first / second), centred, full width (parallax). Opens the lightbox |
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
- TOC: "Title page" first, entries listed under their gallery whatever the
  block's sub-link setting (`preprocessPalefireModularToc()`); no hide/show
  button. `modular-toc.js` picks its edge cases from the shown gallery.
- Motion (`_exhibition.scss`): title page image, kicker, title, credit and
  prompt come in one after another; lead bands open with a curtain and
  pan from the image's top to its bottom over the first 70% of a screen of
  scrolling (`animation-timeline: scroll(root)`), full-width figures pan
  while they cross the screen (`view()`); "Show whole image" leads fade up
  uncropped; side figures rise in. All off under reduced motion, where a
  band rests on "Keep in view when cropped".
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

### 2026-10-21

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
