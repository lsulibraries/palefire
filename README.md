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
| `palefire_exhibit_redesign.php` | Builds "A New Look for LSU Libraries", the site redesign before and after, as an unpublished exhibition (`-- images=<Redesign screenshots folder> path=/v2/exhibitions/redesign`); `edition=staff` builds the staff page, which adds four staff-only galleries under its own title (`path=/v2/exhibitions/redesign-staff`, access restricted in the UI). Both share the 20 screenshots' media items |
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
- **Cover** (`.pf-landing--cover`, `.pf-landing__lead--hero`): the video is
  a band behind the title, intro and search, washed into the page colour
  like an exhibition cover (solid at its foot, from the left under the
  text), fading into the wash as it scrolls up (scroll-driven; off with
  reduced motion). No glass panel and no snap: the page scrolls on through
  the map in one flow. Title with the intro under it (keep the intro to a
  sentence or two), a 4rem
  search field with an accent-filled Search button and suggested searches
  under it (the optional Landing hero field `field_search_suggestions`, Text
  (plain), any number of values; without it, built-in ones), then At a
  glance (`.pf-today`): today's hours (`js/visit-hours.js`), Next
  event (`js/hero-event.js`, from the events carousel's or From the stacks'
  calendar; "No upcoming events" when there are none), the exhibitions on
  view, and the latest blog post. Every cell is a label, one value line and
  one muted line; four across when the panel is 64rem wide, two by two from
  30rem, stacked below. The Quick Guide follows.
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
  the Visit panel's glass.

### Background video

`js/landing-video.js`. With two or more videos, two `<video>` layers
crossfade so there is never black between clips (`--pf-landing-fade` and
`FADE_MS`, keep equal). The button pauses/plays both, shows a progress line and
pauses the clip off screen. The video layer is fixed to the viewport and
clipped at the section's foot; on the hero it is the cover band instead
(absolute, watched itself for off screen). Reduced motion and Settings > Pause background
videos start it paused.

### Lead → map

Not on the hero (its cover scrolls on in one flow). Other landings with a
lead and a map:

- `js/landing-snap.js`: from md, a wheel/key step down from the lead's end
  glides to the map and back up. A lead taller than the window scrolls freely
  until its end is halfway up the window. Touch and phones are left to the
  browser.

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
`index` is a numbered list with no pictures. `masonry` has a Grid / Masonry /
List switch (`js/tile-views.js`, remembered per browser); Grid is the
default and shows the label under the picture. Each card: the title (h3), the
cover (a Tile's Tile Cover; a Modular page's Image cover or landing poster)
through the
`tile_showcase_portrait`/`_landscape` responsive styles, its Category terms as
the label, and its link. Layouts use container queries; the staggered row is a
swipeable strip under 60rem. `js/tile-reveal.js` animates cards in on scroll
(nothing hidden without JS or with reduced motion). A scrim keeps white titles
at 4.5:1 on any image. A card with no cover draws its page instead, as a
desktop shows it (the "On this page" rail, then the title, intro and first
sections; `_palefire_page_outline()`), lying at an angle and blurred, in the
reading swatch's colours.

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
- **Ordering Copies from Special Collections** (`palefire_guide_duplication.php`):
  the Duplication tile on lib.lsu.edu/special as a guide: prices, how to order,
  copyright and citation. Its alias defaults to `/v2/special/guides/duplication`,
  where the landing's "Order prices" button points; publish it after review.
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
title page, then every gallery (section) in turn, as one scrolled document.

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

- `js/exhibition.js`: every gallery shown, one after another. "Begin" and
  any `#link` in the text (an entry, a source) glide to the target and focus
  it; the address follows without a history entry. The TOC scrolls as on
  other pages; a link to a wall's entry opens it in the object view, its
  tile scrolled into sight first. The pinned opening ("Full screen, then
  pan") runs on its gallery's own view timeline (`--pf-opener`, inset by
  the header), so it plays wherever the gallery sits in the page.
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

### A New Look for LSU Libraries

`scripts/data/redesign.json` holds the redesign exhibition in two editions.
The **public** edition has Introduction, Special Collections, Guides,
Exhibitions and Made for Every Reader. The **staff** edition adds the
galleries marked `"staff": true` (Building a Guide, Building an Exhibition,
Standards Behind the Design, How Changes Reach the Site) and uses `staff`'s
title, subtitle and intro. Restrict the staff page's access in the Drupal UI.

- **Pairs:** Special Collections, Guides and Exhibitions are walls. Each
  entry is an image group of two figures captioned "Before" and "After", so
  its tile reads "Before · After" and opens the Wipe / Fade comparison. The
  group caption gives both addresses and dates.
- **Screenshots** ("Redesign screenshots", 20 PNGs): the first screen of
  each page at 1440 × 900 and at 375 × 812 (2×), captured 23 October 2026
  in headless Edge over CDP. The old pages are lib.lsu.edu/special, the
  Systematic Reviews LibGuide, /audubon/introduction (live: the Wayback
  Machine has no 2025–2026 copy, so its mis-encoded text shows as it
  does now), and the Wayback Machine's 13 March 2026 copies of
  /aves/introduction and /occult/introduction. The old site's cookie
  banner and chat pop-up were hidden, not accepted. Alt text is in the data.
- **To fill:** the `[Editor: …]` notes (credit, feedback link, contacts,
  redirect plan) and screenshots for Made for Every Reader.

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

### 2026-10-26

- **LF line endings everywhere.** `.gitattributes` (`* text=auto eol=lf`, binaries left alone) and `.editorconfig` (LF, UTF-8, final newline, two-space indents). The repository already stored every file in LF, but Git for Windows' system-wide `core.autocrlf=true` had left 46 working copies in CRLF, so editing one line showed the whole file as changed. Why: one line-ending rule for every editor and checkout.

- **Page transitions load missing libraries instead of reloading.** `js/palefire-swup.js` (Missing assets): when the next page needs libraries this one hasn't loaded, they're added before the swap, as an aggregate of only those libraries from Drupal's asset route (`include` = the next page's libraries less this one's, `exclude` = this one's, dependencies resolved by Drupal, so core never runs twice). Each group is fetched and added in order (CSS, header scripts, footer scripts); files outside aggregates are added as they are; `ajaxPageState.libraries` becomes the union. Before, such a visit faded out, fetched the page, then reloaded it in full: most links from /v2/special did. A full load stays the fallback if anything fails, or for pages with inline scripts in their content; exhibitions still load normally. Checked on lib.lsu.edu: /v2/special to Staff Favorites, the exhibitions list, Archives and back, all swapped in place with no errors; Staff Favorites to /v2/special loads the ten landing behaviors (two script groups, 4 and 55 KB) and the video, next event, hours, map, TOC and tile reveals match a direct load. One run in four fell back to a full load (the safety net).

- **Exhibitions load normally; a loading line for slow page changes.**
  - `js/palefire-swup.js`: links to an exhibition (`/…/exhibitions/<name>`, not the list) skip Swup. Exhibitions need scripts other pages don't load, so Swup faded the page out, fetched the exhibition, found it incompatible and loaded it a second time, with nothing on screen in between: why Audubon looked broken from /v2/special. Any other page that falls back to a full load is remembered for the session (`sessionStorage`) and loaded normally from the start next time.
  - Loading line (`.pf-loading`, `_header.scss`): 2px at the header's foot in the swatch's highlight, a short bar sliding across, while the next page loads, a Swup visit or a normal one (a same-site link Swup skips; not new tabs, downloads or same-page anchors). Hidden for the first 300ms, so quick visits never flash it; after a second screen readers hear "Loading…". Reduced motion: no slide, the line breathes. Cleared when a page comes back from the back/forward cache. Checked on lib.lsu.edu: the Audubon link now bypasses Swup, and the line is hidden at the click and shows, sliding, by 500ms.

- **A softer top wash; banner categories under the picture; shorter Quick Start buttons; smooth hatching.**
  - Cover (`_landing-features.scss`, `pf-cover-top-wash()`): the wash under the clear header and bar keeps 70% behind their words (4.5:1 for white over a white frame) but no longer stops dead 1.5rem below the bar, which read as a band: it eases out (a cosine) over the next 12rem.
  - Hero banners: the category is a caption under the picture, in the section's colours and the mono label voice, as on the tile showcase's Grid view; the title stays on the picture over a scrim of its own (85% black at the foot). The picture is its own box (`--pf-banner-ratio`, `--pf-banner-shadow`). Under 44rem the blog post stays a line of text.
  - Quick Start: "Order prices" is "Prices" and "See hours" is "Hours". The buttons' wrap points follow the shorter labels (one line from 396px, was 474px; one column under 302px, was 318px).
  - Hatching (`base/_mixins.scss`, `pf-hatch()`): the 1px diagonal lines (Visit's "Show map", the hero event's waiting square, From the stacks, placeholder frames) and the Hours drawer's closed-day stripes get half-pixel soft edges. Hard gradient stops aren't anti-aliased, so 45-degree lines came out uneven, worst at 125% and 150% zoom.

- **Hero cover: the video holds still until the Quick Start.** The cover's video is pinned (sticky, a screen tall) while the title, search and banners scroll up over it, and leaves with the banners as "01" arrives, fading as before. The media layer now spans the banners' row too, and the first screen's height it used to set is kept by a spacer (the lead's `::before`), so nothing on the first screen moves (checked at 1440, 1024, 768 and 320: every block where it was, no horizontal scroll). The washes stay with the words they serve; from lg the bottom fade sits under the banners at fixed depths (solid 2rem, gone by 28rem). How long the video holds depends on the screen: 334px of scrolling at 1440×900, little at 1024×900, where the banners already end at the fold. Reduced motion and Settings > Reduce motion keep the band that simply scrolls. Why: more time with the video.

- **Clear header: no shadows, and the cover's top wash only as deep as the header and bar.** While the header is clear it has no drop shadow, and neither it nor the bar has the page-colour halo round its words, logo and icons (they read as smudges over the video); the glass and shadow come back once the page scrolls. The cover's top wash, which faded out over about a quarter of the screen (about 260px at 1440), now covers the header and the bar (70%, which keeps their white words at 4.5:1 over a white frame) and is gone 1.5rem below them (105px at 1440).

- **Landing bar: the breadcrumb and TOC button back on the left.** The bar is as it was before today: the TOC button over the logo's column, the breadcrumb starting under the menu's first item, the video (or zoom) button at the far end. `js/modular-toc-collapse.js` no longer adds a copy of the button to the bar. The clear header over background videos and images stays.

- **Header: the logo back to its size; clear over any background video or image.**
  - The logo no longer grows across the bar (it read badly); the header is as before, with the bar's breadcrumb and TOC button still on the right.
  - Clear header (`_landing-features.scss`): no glass, fill or rule at the top of the page whenever the first section has a landing background video (the hero's cover among them) or a background image, not only on the cover; glass again once scrolled. The cover's video layer now reaches up under the header (it stopped at the bar, so the clear header showed the plain page colour), and its top wash is lighter (72% to 70% over the first 10%, was 88% to 80%), the least that keeps the header's and bar's white words at 4.5:1 over a white frame, so the video shows through. Why: the header still read as a filled bar over the video.

- **The logo across the landing bar, a clear header on the cover, level hero banners.**
  - Logo and bar (`_landing-features.scss`, `js/modular-toc-collapse.js`): from lg, on a page with the landing's sub-header bar (landings, an exhibition's title page), while the page is at its top (no `.pf-bar-stuck`), the logo is a third larger and centred on the header and the bar together, with no rule between them. It eases back into the header's row once the page scrolls (a transform, so no layout shift; none with reduced motion). The breadcrumb and a copy of the TOC's "show" button (`.pf-landing__toc`, added by the script while the TOC is hidden) sit at the bar's right end, before the video or zoom button; the floating TOC button over the logo's column is gone there. Pressing either TOC button moves focus to the one that replaces it. The breadcrumb list's own room for the video button is dropped inside the bar, which already keeps it. Below lg nothing changes.
  - Clear header (`_landing-features.scss`): on the hero's cover, at the top of the page, the header has no glass, fill or rule, like the bar; its words keep a halo of the page colour, and the logo and icons a matching drop shadow, for contrast over the video. Glass again once scrolled; reduce transparency keeps it throughout.
  - Hero banners: no vertical stagger. Every banner stands level (no drop on the second and fourth, or on the right column below 44rem), the grid loses the room it kept under the drop, and the rise-in comes up from below for all of them, still left to right.
  - Checked on lib.lsu.edu with the local CSS and scripts at 1440, 1024, 768 and 320: no horizontal scroll; header and bar change state on scroll.

- **Hero banners: the tint is the page's own colour.** Each picture is grey (`contrast(1.1)`) with the cover's page colour (`--pf-cover-bg`, so the swatch's) washed over it at 50%, in place of the accent multiplied over it, so the row sinks into the dark ground around it until a banner is hovered or focused. The scrim and the white words are unchanged. Why: the gold read as a separate colour on the page rather than part of it.
- **Exhibitions as one scrolled document; still hero banners, toned until hover; tiles open on Grid with the label under the picture.**
  - Exhibitions (`js/exhibition.js`, `_exhibition.scss`, `exhibit-cover.html.twig`): every gallery shows, one after another under the title page, with a hairline between them. Gone: hiding all but one gallery, the fade between galleries, the previous / next links, the folded running head and its breadcrumb crumb, and the history entries per gallery. "Begin" and in-text `#links` glide and focus; TOC links to a wall's entries still open the object view. The pinned opening's fade and pan moved from the page's scroll (which assumed the gallery opened at the top) to the gallery's own view timeline, inset by the header; measured on Occult, the stage pins at 0 and the pan ends exactly at 40svh plus `--pf-pan`, as before. The bar's zoom button now opens only the title page's cover, so a gallery's opening shows its own enlarge mark again. Why: the exhibition reads as one document by default.
  - Hero banners (`_landing-features.scss`): no more scroll drift or cross (the row and its stagger hold still); the pictures still pan in their frames. Each picture is grey and a little dimmed (`brightness(.8) contrast(1.1)`), with the swatch's accent multiplied over it (lifted with a third white, so Cream's purple tints rather than blacks out); multiply only darkens, so the white words keep or gain contrast. Hover or focus fades its own colours back in as it zooms. Why: the row reads as one set in the page's colours, and the colour reveal rewards hover.
  - Tile showcase, masonry (`paragraph--tile-showcase.html.twig`, `js/tile-views.js`, `_tile-showcase.scss`): Grid is the first and default view; the old "Default" is now "Masonry". A pick saved as "default" is no longer a view, so those visitors start on Grid too. In Grid the Category label sits under the picture as a mono caption in the section's colours (the title stays on the picture); the scrim and shadow are on the picture. Masonry and List are unchanged.

### 2026-10-23

- **Rooms and access tidied; See hours button; tiles fill to the foot; one label per card.**
  - Rooms and access (`map-key.html.twig`, `_landing-swiss.scss`, `_landing-features.scss`): no hairline under the drawing (03's rule opens the section), no elevator mark in its first column for now (the drawing keeps it), no closing line under each column's last room, and more room round the rule before Visit (2–3rem above, 1–1.5rem below; it was 20px and 14px).
  - Quick Start: the hours button reads "● See hours ↓" (status words for screen readers only, so the dot's colour never carries it alone). When the three buttons don't fit one line (about 474px), a container query lays them out as two columns the width of Full guide and Order prices with See hours across both, its edges on theirs; under about 318px, one column at one width. Breakpoints are in rem from the measured labels; a much longer translation would need them moved.
  - Tiles (`_tile-showcase.scss`): the banner-voice cards' picture stopped 2px above the foot, the same strip of grey ground that read as a border on the hero banners; it now fills to the foot.
  - Labels (`palefire.theme`, `paragraph--tile-showcase.html.twig`): tiles show one label for their Category terms, as the hero banners do, gold on hover in the banners' highlight. `_palefire_card_label()` joins the terms with " + ", or a space when one is Exhibition ("Digital Exhibition", "Physical Exhibition"), for the banners, tiles and From the stacks alike.
  - Checked at 320, 400 and 1440 on the live page with this markup and CSS swapped in: no horizontal scroll; See hours spans exactly the pair above at 400 (20–338px) and all three are one width at 320. The PHP wasn't run locally.

- **03 Rooms and access: the map's legend as an indexed section; index numbers no longer typed by hand.**
  - Why: in the hero's Visit panel the legend's toggle ("Legend & spaces", bold sans capitals, a heavy chevron) was the one row on the page outside the mono label voice, it started collapsed across the full width, and its note floated alone between two rules.
  - `map-key.html.twig`, `_landing-swiss.scss`, `building-map.js`: in the hero (`map.visit`) the toggle is an index rule, "03 Rooms and access", with the count and +/− at its right end in the rule link's voice; it stays open, and the body is an index in three columns from 768px (the note and legend, then Walk in and By appointment, group headings as grey mono labels, rooms in white), stacked below. Maps elsewhere keep their own key.
  - Numbering (`palefire.theme`, `landing.html.twig`, the scroll-section field template): `_palefire_hero_rules()` lists the hero's rules from the facts its templates read (lead shown, published map, Visit card); the hero numbers them from it (`hero_index`, and `map.visit.index` for the key) and the sections after count on from its length, so the Tile showcases are now 04 and 05. The literal '01' and '02' are gone. A CSS counter was tried first: the map and the cover are size containers, which scope counters, so the key read 01.
  - Checked on the live page with this markup and CSS swapped in at 400, 768, 1024 and 1440: 01 to 05 in order, no horizontal scroll, group headings grey (they are h4s, which Twilight's content-heading gold reached). The PHP wasn't run locally (no PHP here); after `drush cr`, the hero should read 01, 02, then 03 Rooms and access, then 04 and 05.

- **Twilight text in two tones; hours links, badge and banners tidied.**
  - Note: the SCSS for the Twilight colours, the search focus fix and the white Stacks titles went out early inside 1b28303 (its `git add scss/components` picked them up mid-edit). This entry covers them and finishes the rest: the footer links, section titles and templates.
  - Twilight (`_reading-themes.scss`): running text is now the soft grey the labels use (`#b7b3ba`, 7.4:1 on the page, 6.7:1 on surfaces; was near-white `#edebee`); headings (`--bs-heading-color`), links and bold (a `strong` key) keep near-white. The grey can't go dimmer for a third tier: over a white video frame it is already near 4.5:1. Checked by rendering the page with text hidden and measuring the brightest pixel under every grey text box at 320 and 1440 against the current colours: no new failures (lowest 5.1:1, median 7.5:1). Footer links, landing section titles, the index-rule links and the Stacks titles take the heading colour; the Quick Start's intro and notes take the grey (the video is fully washed out behind it).
  - Hero hours (`landing.html.twig`, `_landing-features.scss`): today's hours are the link to the week (the separate "This week's hours" is gone; its name finishes "this week's hours" for screen readers). The status badge loses its frame for a solid ground: the swatch's accent while open or closing soon (black on pale gold), the hero links' plate while closed or loading; a frame only in forced colours.
  - Quick Start: a third button, "● Open now · See hours ↓", jumps to the week too; the status shows once LibCal answers and the button goes if it fails.
  - `visit-hours.js`: the jump now focuses the status line after the glide ends (one short correction if the map settled under it). Focusing at the start set off the building map's own focus scroll mid-entrance, which stopped the glide at 768 to 1199px.
  - Hero search: focused, the gold hover line folds away, so the foot matches the top (2px frame all round, not 4px at the foot).
  - Banners: the picture runs to the foot (the 2px strip of dark ground above it read as a bottom border), and the next event's scrim eases off sooner, so its foot is no longer a flat black.
  - Footer: the other building's name is its link (no arrow); the "… home →" line under its hours is gone.
  - Map: the "Tap a numbered room" hint icon is the duotone bullseye-pointer.
  - Checked at 320, 768, 1024 and 1440 on the live page with this markup, CSS and JS swapped in: no horizontal scroll; the jump lands 97px down (clear of the bar) with focus there at every width.

- **Instagram moves to From the stacks; the stage runs the lists' height.**
  - Why: Instagram didn't belong in Hours and directions, and the stage stopped short of the lists beside it (4:5, capped at 36rem).
  - `paragraph--landing-stacks.html.twig`, `stacks-stage.js`, `_stacks.scss`: the stage now rests on an Instagram placeholder in the old hatch (the mark and label, then hatched bars for the post's title, two lines of description and the link) instead of the featured cover. Hovering or focusing an item still shows its picture (the feature's cover included), and leaving the lists brings Instagram back. Beside the lists the stage stretches to their height (24rem floor), top on the first heading, foot on the last rule; stacked, it stays a 3:2 band. Still `aria-hidden`: the real embed will need its link outside the stage.
  - `visit-strip.html.twig`, `_visit.scss`, `_landing-features.scss`, `_landing-swiss.scss`: the Instagram cell is gone. The strip is hours | mini map (equal halves from 1200px; on the hero cover two columns of four, two of five).
  - Checked on the live page with this markup and CSS swapped in at 320, 768, 1024 and 1440: no horizontal scroll; stage and lists the same height (633/633 at 1024, 626/626 at 1440); hover shows the feature, leaving returns to Instagram.

- **Weekly hours: a link from the hero, and the week always open.**
  - Why: the week sat in a collapsed "This week" in the Visit Hill panel (shut on phones), several screens down, with nothing at the top pointing to it; the footer, further still, keeps today's hours only.
  - `landing.html.twig`, `_landing-features.scss`: the hero's hours line ends with "This week's hours ↓" (a dot before it from md; on phones the building takes its own line, so the line no longer grows when LibCal answers: CLS at 320 went from 0.07 to 0). Hairline underline, gold on hover, the arrow dips; hidden if LibCal fails.
  - `visit-strip.html.twig`, `_visit.scss`, `_landing-swiss.scss`: the `<details>` is now a heading and a ruled Monday-to-Sunday timetable (day, date, hours): today weighted with a gold underline and `aria-current="date"`, past days muted. Seven blank rows hold its height until LibCal answers, then it fades in. The status line is the link's target (`{visit_id}-hours`, `tabindex="-1"`, clear of the landing bar).
  - `visit-hours.js`: the week runs Monday to Sunday, and on Sundays shows next week ("Next week"), since LibCal's data starts on the Sunday. A day LibCal doesn't list reads "Not posted", not "Closed". The link glides to the panel (instant with reduced motion), focuses the status line and sets the hash. The lg-only open/close toggle is gone.
  - Checked on the live page with this markup, CSS and JS swapped in at 320, 768, 1024 and 1440: no horizontal scroll, the jump lands 16px under the bar with focus there, Sunday rolls over, reduced motion jumps at once.

- **Hero links: no blur under the stuck bar (flicker fix).**
  - `_landing-features.scss`: the links' plate flickered as it scrolled under the sticky bar and header, a Chromium glitch with one backdrop-filter passing under another (confirmed in DevTools: turning off the plate's blur stopped it). A scroll-driven animation (a view timeline inset by the header and bar) now drops the plate's blur as its top reaches the bar's foot, half a rem early, and scrolling back restores it. The 65% fill keeps the words at AA either way. Browsers without scroll timelines keep the blur. Checked at 1440 with this CSS injected: blurred at the top and 40px below the bar, unblurred under it.

- **Hero search: a gold line draws in on hover.**
  - `_landing-features.scss`: hovering or focusing the hero's search draws a 2px line in the Search button's gold along the field's foot, from the start to the button (the input's own background, so it ends at the button at every width; mirrored in RTL), and the placeholder brightens toward the text colour. With reduced motion (or the Settings switch) the line appears at once. Why: the search needed a cue on hover; the line ties the field to its gold button without moving anything. Checked at 1440 on the live page with this CSS injected.

- **Hero search: placeholder in the intro's type.**
  - `_landing-features.scss`: the hero's search field (placeholder and typed words) takes the intro's size (`--pf-hero-text`) and regular weight instead of the larger 300 the other landings' fields use. Why: the light weight was the one 300 on the hero and read out of step with the intro beside it.

- **TOC icon: the rail and its marker.**
  - `page.html.twig`, `_modular.scss`: the list icon (TOC toggle and phone button) redrawn as the sidebar looks: the rail's top third in the bright text colour (`--pf-toc-active`, as the TOC's active link; `.pf-toc-icon__mark`), the rest at 35%, then three entries left-aligned with ragged ends, all at one 1.5px weight. Why: the previous five-part 1px drawing looked thin and busy at 16px. On hover the bars brighten to match the marker.

- **Hero spacing restored; search shadow; TOC icon redrawn.**
  - `_landing-features.scss`: the hero's top block (a grid from 40rem since the links moved beside the intro) took the older `xl` rule's 2rem row gap, which stacked 32px more under the title and under the hours. The row gap is now 0, so their own margins space them as before. The hero's search (field and button) has the banners' soft shadow; focus keeps its gold ring over it.
  - `page.html.twig`: the list icon (TOC toggle and phone button) on whole pixels: a faint rail, a heading line, then three entries indented and left-aligned with ragged ends, four rows 2px apart, 1px lines as the header's icons, the drawing centred in its box. Why: the old icon's 1.2-unit bars blurred at 16px, its rows were uneven and its short bars were right-aligned, unlike a TOC.

- **Hero links: glass plate, flush cells, gold hover; Log in over the Search button.**
  - `_landing-features.scss`: the links' plate takes the glass blur again (still 65%, lighter than the search's 80%), so the words read over busy frames. From 80rem the three cells are flush (no gutters): the last is one banner wide, the others a banner and a gutter, so a hover fills a link's whole cell, the hairlines sit on the cells' edges, and Log in's cell and words start where the Search button's do (1130px and 1150px at 1440, both). Hover turns the words gold over the deeper cell (about 82% in all: 5.9:1 for gold over a white frame).
  - Why: hovers filled only part of a column (the gutter stayed light), Log in sat a gutter left of the Search button, and the plate alone was hard to read over bright frames. Checked at 1440 on the live page with this CSS injected.

- **Hero links on one lighter plate; landing bar lines up with the header; map entrance as an opening.**
  - `_landing-features.scss`: University Archives, Oral History and Log in share one plate split by hairlines, beside the intro and as tall as it (a grid row from 40rem: rows in the right half; from 80rem three columns over banners 3-5), words and arrows centred. Phones: rows under the intro. The plate is the page colour at 65%, no glass: lighter than the search's 80% so the two read apart, and the least that keeps white words at 4.5:1 over a white frame (4.7:1). Log in's words go white (gold would need 75%); its arrow keeps the gold. Hover deepens a link's share of the plate. Why: the plateless index was hard to read over bright frames.
  - `_header.scss`, `_modular.scss`, `_landing-features.scss` (lg and up, where the menu shows): the landing bar's TOC button is as wide as the logo (`--pf-logo-w`, which follows the compact header), its icon on the logo's edge, with a clear 1rem strip under it that also takes clicks; the breadcrumb starts where "Help with" does (gutter + logo + `$pf-brand-gap`). Hover brightens the icon instead of filling the wider box.
  - `hill-memorial-map.svg.twig`, `building-map.js`, `_building-map.scss`: the front wall stops either side of the doorway, each end closed by a short edge, instead of the porch jutting out; the entrance arrow and its centring script are gone.

- **Guides: room between the breadcrumb and the title.**
  - `_guide.scss`: on guides the `<h1>` sits `clamp(2rem, 1rem + 3vw, 3.5rem)` under the breadcrumb (was 0.85rem), more than the 1rem between the title and the intro. A guide with no breadcrumb keeps its title on the first line.
  - Why: the breadcrumb is a running head; set tight over the title it read as part of it. The sticky landing bar was considered and left to landings: a reading page needs no chrome pinned over it.

- **Hero links: a ruled index beside the intro; the search loses its frame.**
  - `_landing-features.scss`: University Archives, Oral History and Log in drop their framed glass plates and become a hairline index (mono label, arrow, rule gold on hover and focus). Phones: rows under the intro, a rule only between them. From 40rem: the same rows in the intro's right half (a two-column grid, both feet level, so they can't run into the hours). From 80rem: three columns over banners 3-5, a rule on top of each, Log in over the Search button. The words carry the hours line's halo for 4.5:1 on any video frame.
  - The hero's search field has no frame (clear; gold on focus, CanvasText in forced colours): its fill and the Search button mark it, and no rule sits under the links to meet it.
  - Why: the plates matched the search field's look, so the links read as more fields of one form, and the row under the intro left empty space. Checked in headless Edge against the live page with local CSS at 320, 768, 1024 and 1440; no horizontal scroll.

- **Price list fixes: the prose width actually applies; no empty line in the fee.**
  - `_guide.scss`: the price list's block test was `:has(table:not(:has(…)))`, and `:has()` can't nest, so browsers dropped the rule and the list (and the text after it) ran to the guide's 52rem. Now `:has(table):not(:has(thead, tr > :nth-child(3)))`: 672px, the intro's width.
  - The fee's label is a block, so the `<br>` after it left an empty line over "Processing fee"; that `<br>` is hidden.
  - Checked on the live page with this CSS injected in Edge at 1568 wide. No content change: no need to rerun the prices script.

- **Price list: the processing fee as the list's foot; notes muted; prose width.**
  - `palefire_guide_duplication_prices.html`: the fee leaves the rows for a `<tfoot>` ("Added to every order", Processing fee, + $10), and the caption becomes "Prices per item". Notes under an item are `<em>`, since the guide's text format strips `<small>` (live, the notes showed at full size and colour). The script now warns if the format strips `<tfoot>` or `<em>`.
  - `_guide.scss`: the foot sits under a 2px accent rule (the list's one line that isn't a hairline, as a receipt's total), with a mono label and the amount larger, in the title's highlight. Notes (`small` or `em`) are upright and muted. A price list keeps the 42rem prose width, as does the text after it (any block with a table had dropped it, for wide tables' scrolling).
  - Why: the fee applies to every order, so it reads as a charge on top, not one more item. Checked in headless Edge against the live page with local CSS at 1440 and 768.
  - After pulling: `drush php:script themes/custom/palefire/scripts/palefire_guide_duplication_prices.php`, then `drush cr`.

- **Guide titles stop at the prose's edge; balanced headings and pretty text sitewide.**
  - Guides (`_guide.scss`): the `<h1>` is capped at the prose measure (42rem, now `$pf-guide-prose`, shared with `--pf-guide-prose`), so it no longer runs past the intro and blocks; being balanced, a long title breaks into two even lines.
  - Base (`_base.scss`): headings take `text-wrap: balance` and running text (`p`, `li`, `dd`, `dt`, `blockquote`, `figcaption`, table cells) `text-wrap: pretty`, which avoids a lone word on a paragraph's last line. Zero specificity (`:where`), so components that set their own keep it; older browsers wrap as before.
  - Why: on "Ordering Copies from Special Collections" the title ran about 900px past the text column.

- **Hero search: the magnifier on the button, Search in the mono voice; Log in in gold.**
  - Hero only (`includes/landing.html.twig`, `_landing-features.scss`): the Search button's arrow becomes the magnifier, and the magnifier that led the field is gone (the placeholder starts at the field's 1.25rem inset). "Search" is set in the mono label voice of the links over it; the icon holds still on hover (the arrow's nudge read as "go on"). Phones keep the icon alone, as before. Other landings keep the leading magnifier and the arrow.
  - Log in: the gold outline and fill are gone; its words and arrow are in the highlight, its frame the others' hairline (hover brings the frame up to the highlight, as theirs).
  - Verified in Edge with local CSS and the markup injected at 1600 and 375: Search and Log in both on banner 5's edges (x 1206, 230px).

- **Hero links a gutter over the search; TOC toggle unframed; TOC icon squared.**
  - Hero: "Search Special Collections" is for screen readers only (`visually-hidden` on the hero's label heading; it still names the search form), and the title block's bottom margin is the grid gutter, so the three links sit one gutter over the field, as the links and banners are spaced (32px at 1600, was about 51px).
  - TOC hide button (`_modular.scss`): no frame while the TOC is open (a box there read as misplaced beside the title and the bar), a faint fill on hover. The closed glass button on other pages keeps its frame.
  - TOC icon (`page.html.twig`): the rail is now the lines' 1.2 thick and runs exactly from the top line's top to the bottom line's foot, the mark an open 11.2 square centred in the 16 box.
  - Verified in Edge with local CSS and the markup injected: the gap at 1600 with the TOC open; the icon at 4x.

- **Hero: three links in place of the week's hours, Search a banner wide; footer: directions; a simpler TOC icon.**
  - Hero (`includes/landing.html.twig`, `_landing-features.scss`): University Archives (`/v2/special/archives`), Oral History (`/v2/special/oralhistory`) and Log in (Aeon, new tab, outlined in the highlight and filled with it on hover) replace the week's timetable. In the Quick Start buttons' voice (hairline frame, mono label, arrow at the end) on the search field's glass. From 80rem each is exactly a hero banner wide, in columns 3-5 under banners 3-5, their foot on the intro's; narrower, a row of three under the intro; one to a line on phones. "Sign in to request materials" is gone from the intro (Log in replaces it), with its styles.
  - Search button: one banner wide wherever the banners are five to a row (44rem and up), both edges on banner 5's; the word at its start, the arrow at its end.
  - Footer: a Where row under Today, the building's name linking to Google Maps directions (place.query in the footers map), the arrow turned up-right as it leaves the site; its name adds ", directions in Google Maps". Its own row rather than Today's: that link's text would have been the hours, not where it goes (WCAG 2.4.4).
  - TOC icon (both buttons, `page.html.twig`): the three diamonds become a faint full-height bar (40%, Font Awesome duotone's second tone), the TOC's rail, beside the same three lines.
  - Also ships the Quick Start/tile-label sources (`palefire.theme`, `paragraph--tile-showcase.html.twig`, `_guide.scss`, `_landing-features.scss`, `_tile-showcase.scss`) whose CSS and README entry went out in `53b28bd`/`f821104` without them: until these land, live cards print a bare `.pf-tile__label` and the label falls under the title.
  - Verified in Edge with local CSS and the new markup injected: links at x 681/943/1206, 230px each at 1600 (banners 3-5 exactly); Search 230px on banner 5; 1440, 1024 and 375 laid out without horizontal scroll; the TOC icon at 4x.

- **Redesign exhibition: AVES added, 2026 "before" pages.** The Exhibitions wall gains AVES (desktop and phone pairs). The old Audubon and Occult Science pages are now the version from just before the redesign (/audubon/introduction live, /occult/introduction from the Wayback Machine, 13 March 2026), not the 2021 page and the 2011 blog. The new files have new names (`exhibit-*-before-2026-*`), so a rebuild imports them instead of reusing the older media items.
  - Why: the redesign replaced the 2025–2026 pages, so those are the honest "before".
- **Footer: the page's Swiss voice, and a curtain reveal.**
  - Restyled (`_footer.scss`, `includes/footer.html.twig`), as mockup A on the footer canvas: labels (kicker, facts, column titles, copyright, legal links) in the mono UI voice (`--pf-toc-font`, so the readable font when chosen); the kicker as an index rule across the lead; links plain at rest, the accent and a hairline underline on hover; hours marks as small diamonds, as the request paths'; Instagram and Facebook in square hairline cells.
  - Call and Ask as ruled rows (mockup B): each link covers its whole row, with an arrow at the end; on hover the accent, a gold rule under the row and the arrow nudged on; the row takes the focus ring. "Plan a research visit" is a square gold block (B) in the mono voice; "Main Library home" gains an arrow.
  - Curtain: from 62rem wide and 50rem tall, where the whole footer (about 45rem) fits the window, the footer holds still at the window's foot while the page (`#swup`, opaque, one layer up, a soft shade along its edge) slides up off it. While focus is inside the footer it rejoins the flow, so a link tabbed to under the page is scrolled into view. Not with reduced motion (OS or the site setting); narrower or shorter windows scroll it in as before.
  - Why: the round pill, gold underlined links and sans labels didn't match the rest of the theme; the curtain marks the footer as the end of the page.
  - Verified in Edge with local CSS and the new markup injected: 320, 768, 1024 and 1440 with no horizontal scroll; every footer link type hit-tests as clickable once revealed; focusing the covered button scrolls it fully into view.

- **Redesign exhibition, public and staff editions.** `palefire_exhibit_redesign.php` and `data/redesign.json` build "A New Look for LSU Libraries": the old and new Special Collections, guide and exhibition pages as Before / After pairs on wall galleries, at desktop and phone sizes. `edition=staff` builds a second page, the same galleries plus four for editors.
  - Why: to show readers and staff what changed, with the exhibition's own comparison viewer, and to keep the two editions in step from one data file.
- **Quick Start: paths ride along on tall screens, diamonds hold lit; tiles: a square label per Category term.**
  - Quick Start (64rem and up, screens 46rem tall and up): A, B and C stick under the header and the bar while the longer first column (heading, intro, Before you visit) scrolls past, until the guide ends. Where the paths are the taller column (wide windows), nothing changes.
  - Request paths reveal (`_guide.scss`): each diamond now stays lit once the line reaches it (0.6s, 1.2s, 1.45s, 1.7s); with all four lit (1.97s) they hold 1s, then go out together over 0.45s. One set of keyframes per stop and no fill, so hover works after. Not with reduced motion.
  - Tile showcase cards: every Category term (field_category) gets its own label (`labels` in `palefire_preprocess_paragraph__tile_showcase()`, joined only on the index's rows), read as ", Exhibition, Digital" after the title. Labels on the cards and the hero banners are now square, in the page's mono label voice (as the section eyebrows and the Quick Start buttons), white on black, gold on hover.
  - Why: the paths left a long gap beside the first column on narrower desktops; the brief pings read as flicker rather than a path being built; the rounded pills didn't match the theme's square, Swiss look, and a second term had nowhere to go.
  - Verified in Edge with local CSS: sticky top 105px at 1280×1000; outline colours sampled every 150ms; two labels on AVES at 1440. A second term only shows once it's on the node's published revision and the term is published: live AVES currently lists only "Exhibition".

- **Hill Memorial map: the summary runs further into the drawing's empty corner.** On the hero's desktop map (64rem and up, no event card) the summary is now 47% of the map's width (was 38% from 1440px windows up, capped by the hero's two-column and 62ch limits, which no longer apply here) and set at 1.57cqi (20px on the widest map, 16px on the narrowest), so it scales with the drawing and breaks into the same seven lines at every size.
  - Measured on the live page in Edge, with the drawing's own shapes (`isPointInFill` / `isPointInStroke`) against each line of text, at 1180 to 2560px: 36–44px clear of every shape, no horizontal scroll. 50% and up brings the seventh line within 24px of the upper Gallery; under 46% the text takes eight or nine lines and reaches it from above. Phones, tablets and 1024px still set the summary above the drawing.
  - Why: the text stopped well short of the empty space beside it, and ran nine lines deep toward the Gallery.

- **Hero banners and gallery cards: no hairline of picture at the foot.**
  - Each picture now stops 2px above its card's foot, on a clip of its own (`.pf-hero-banner__media`, `.pf-tile--banner .pf-tile__media`), so the last rows are the card's dark ground under the scrim's darkest stop. On the banners the pan moves to the `img` inside that clip, which holds still.
  - Shipped in `33a2b02` (which reused the masonry commit's message); this entry and the guide sources below follow it.
  - Why: at fractional scales (125%) the composited picture and the scrim rounded to different device rows, leaving a light line at the foot at some scroll positions. Measured clean in Edge at 100%, 125% and 150%.

- **Duplication guide: prices as a price list; request paths: diamonds light up as the line reaches them.**
  - Guide text: a table with two columns and no header row is now set as a price list (`_guide.scss`): no box or minimum width, rules between rows, the item in body weight with an optional `<small>` detail under it, the price bold, right-aligned and on one line with even figures. Tables with a header row (Systematic Reviews) are unchanged. Text formats drop classes, so the table's shape is the marker.
  - The duplication guide's Prices section is that table plus one note (`scripts/palefire_guide_duplication_prices.html`), shared by `palefire_guide_duplication.php` (new builds) and the new `palefire_guide_duplication_prices.php`, which replaces the Prices section's blocks on the live page as a new revision (the old one stays under Revisions).
  - Request paths reveal: each diamond briefly takes its hover look (accent outline, brighter icon) as the line reaches it, the first as the line leaves it (0.6s, then 1.2s, 1.45s, 1.7s; 0.9s each). Measured in Edge over the DevTools protocol. Not with reduced motion.
  - Why: the prices read as prose rather than a price list; the pulse marks each step as the line connects it.

- **Quick Start Guide: "Full guide" and "Order prices" buttons, a new duplication guide, and a slower line reveal.**
  - Under Before you visit, two square buttons on a hairline in the labels' voice (`.pf-quick-guide__link`), each opening in a new tab with an up-right arrow after it (the name says so for screen readers). Hover takes the highlight and nudges the arrow. "Full guide" uses the Quick Guide's guide URL; "Order prices" goes to `/v2/special/guides/duplication`.
  - New `scripts/palefire_guide_duplication.php` builds that page, unpublished: "Ordering Copies from Special Collections", from the Duplication tile on lib.lsu.edu/special (prices, how to order, copyright, citation). Run it, review, then publish, or the button leads to a page visitors can't see.
  - Request paths: the line between the diamonds now waits for the stops (0.6s), then draws one leg every 0.25s over 0.6s, eased in and out. It used to start 0.2s after each stop and finish with it.
  - Why: the line's drawing was the best part of the reveal and was lost under the stops' fade; the duplication tile's prices had no page of their own in v2.

- **Request paths (Quick Start Guide): the whole step opens its panel.** On a stop with a "how" panel, the label's button now stretches over the whole stop (icon, label and note) plus half a rem on every side (`.pf-paths__peek::after`), and its focus ring goes round that area. Hovering or focusing it turns the diamond's outline the accent colour and brings the icon's second duotone layer up from 40% to 75%. The button's accessible name is unchanged, and stops without a panel are unchanged.
  - Why: only the label text was clickable, a small target next to an icon that looked clickable.

- **Tile showcase: a lighter scrim over no-cover tiles.** Cards that draw their page (`.pf-tile--no-image`) get their own `--pf-tile-scrim`: 55% black up to the title's full height (`--pf-scrim-floor`: 6.5rem on masonry banners, whose titles clamp at three lines; 9rem on stagger and grid, whose titles don't), then easing out over the next 9.5rem. Photo tiles keep the old scrim.
  - Why: the outline's colours are known, so it needs less than a photo. White on the lightest of them (#f8f9fa, the plain light page, with the blurred text counted at full strength) needs 52% black for 4.5:1; light swatches need 47–50%, dark ones 35–44%. 55% leaves a margin.

- **Tile showcase: the no-cover outline is closer and panned right.** The angled page is at 85% (was 70%) and shifted so the "On this page" rail runs half off the card's left edge, and the title and column are cut off at the right.
  - Why: at 70% the page filled only the top of the card, and the full title still read as a repeat; closer in, the crop keeps a hint of the rail and only part of the title.

- **Tile showcase: the no-cover outline is photographed on a desk, title back.** The blurred desktop page now sits tipped away and turned 14° (`rotateX` / `rotateZ` in perspective), zoomed out to 70% so the whole rail and column show with words still cut off at the right edge, under one flat blur. The page's title is back at the top of its column.
  - Why: at an angle the blurred title reads as part of a photographed page rather than a copy of the card's title, and the angle gives the cards depth. One flat blur instead of a depth-of-field stack keeps it to a single filter per card.

- **Tile showcase: the no-cover outline is the desktop page, without the title.** The blurred page now shows the guide as a desktop does, anchored left: the "On this page" rail (its sections, the gold bar on the first), then the intro and sections in a wide column that the card's edge cuts off. The outline's own title is gone. On hover only the content column scrolls; the rail stays, as the real one is sticky.
  - Why: the blurred title repeated the card's title right below it, and the narrow single column read as a phone page.

- **Tile showcase: a card with no cover shows its page, blurred.** Instead of a blank dark card, a tile with no image (most guides) draws the page it links to: its title, intro and first four titled sections with their block titles and opening lines (`_palefire_page_outline()`), set small, blurred and aria-hidden, in the reading swatch's colours (`--pf-g-*`; the section's Bootstrap colours on pages without one). It scales with the card, so the masonry's List thumbnail is the same page in miniature, and drifts up slightly on hover (not with reduced motion). Hidden in forced colours. Every layout but the index, which has no pictures.
  - Why: guides almost never have cover images, and an empty card says nothing about what's inside.

- **Exhibition covers: the long fade is back, with the image still moved right.** From lg the cover image starts at 28% of the head (`$pf-exhibit-cover-media`), and the side wash is solid to 38% and fades out by 92%. The entry below had the image at 44% under a 46% to 84% fade: the subject showed, but nothing of the image was left behind the title, so the head looked cut in two with a flat block of colour beside the picture. Now the image shows faintly behind the title as before (the text gets about the same wash), and a portrait image's centre sits about 14% further right than it did originally.
  - Focal Point preview: `wash-desktop.png` is now 1160 × 1200, the image's frame on a 1440 × 900 screen. Set the "Cover preview: desktop" style's Focal Point Scale and Crop to 1160 × 1200.
  - Why: the shorter fade read as a split, not a fade.

- **Exhibition covers: the image sits right of the text from lg, under a shorter fade.** `.pf-exhibit-cover__media` now starts at 44% of the head's width instead of its left edge (that part was always under the solid wash). The side wash is solid to 46% and fades out by 84% (it was 38% to 94%). Phones are unchanged.
  - A portrait image is shown across its whole width at this size, so its centre used to land in the fade, wherever the focal point was (only the point's height mattered). In the narrower frame, a subject in the middle of the image, such as AVES's cotinga, sits in the clear right side. Checked on Occult, Audubon and the cotinga at 1024 and 1440: the text's right end gets at least as much wash at 1440 as before, and at 1024 the same.
  - Focal Point preview: `images/focal-preview/wash-desktop.png` is now 900 × 1200, only the image's part of the head on a 1440 × 900 screen (`build/focal-preview-washes.js`). Set the "Cover preview: desktop" style's Focal Point Scale and Crop to 900 × 1200 so it lines up.
  - Why: subjects were hidden under the title's wash, and the focal point couldn't move them sideways.

- **Tile showcase: a Masonry layout with a Default / Grid / List switch, and numbered section headers.** For "03 From the Galleries / Off the Walls, Online" on the Special Collections homepage.
  - Masonry cards are in the hero banners' voice: their scrim, a mixed-case title and the pill above. They sit in columns that follow the section's width: 2, 3 from 36rem, 4 from 56rem, 5 from 72rem (230×461 at 1440, a banner's size).
  - Card heights cycle around a banner's 1:2. `js/tile-views.js` packs each card into the shortest column in list order. It also shows the switch (`aria-pressed` buttons) and remembers the visitor's pick in localStorage. The change morphs with View Transitions, except with reduced motion.
  - Grid is even 3:4 cards. List is hairline rows: a thumbnail, the label in mono, the title, the note and an arrow.
  - The new optional fields Eyebrow, Lede and Link add the hero's index rule above the section's heading, with the lede under it. The rule is numbered by position (the hero's 01 and 02 count first) in `palefire_preprocess_field()` and printed by the scroll-section field template.
  - Card images now fall back to an exhibition's cover (`field_exhibit_cover`).
  - Setup: `scripts/palefire_tile_showcase_gallery_setup.php`.
  - Why: a section of exhibitions that reads like the banners, with a choice of view for people who'd rather scan a list.

- **Fix: hero banners' scrim leaving a hairline of bare picture at the edges.** The scrim (`.pf-hero-banner__link::after`) gets `will-change: transform`, so it is composited like the panning picture and the link's clip snaps the same for both. Why: at fractional display scales (125%) the picture's layer was clipped a device pixel wider than the painted scrim, so a light row showed under some banners' feet and a light column at their sides.

- **Hero banners: the picture pan on desktop too, deeper.** The phones' pan (`pf-banner-pan`) now runs at every width, under the row of five's drift and cross: each picture is 3rem taller than its frame at top and foot from 44rem (1.5rem narrower), so it slides 96px through its banner as the banner crosses the screen. The event banner keeps 1.5rem, since a deeper pan crops its flyer's headline. Reduced motion and the Settings drawer's switch still stop it. Why: the pan gave the phone banners depth, and the desktop row wanted the same.

- **Hero banners on phones: a tighter grid, and the pictures move instead of the banners.** Under 44rem of landing (two to a row, phones and portrait tablets) the gutter is `clamp(.625rem, 2.5cqi, 1rem)` (10px at 375, 16px at 768, was 20px), the right column's drop is 1.5rem (was 2.5rem), and the blog line gets .375rem extra under it. The side margins still line up with the title and search. With motion on, the banners stay put as the page scrolls; each picture is 1.5rem taller than its frame at top and foot and pans down through it on the banner's own view timeline (`pf-banner-pan`), not on the event's date panel. The drift, the cross and the Quick Start's lift allowance now apply only from 44rem, where the row of five is unchanged. Why: on phones the cross carried whole banners past each other, so the right column rode above the left mid-scroll, and the wide gutters made the two columns read as loose pieces.

### 2026-10-22

- **Footer: no frame around Today / Call / Ask.** The rules above the first fact and below the last are gone, so only the rules between them remain, and the first fact starts on the kicker's line. Why: the frame boxed the facts in against the open intro beside them.
- **Visit strip: three frames of one height, and "Hours and directions".** At 1200px and up (on the landing cover, from 64rem) the Instagram frame, the hours and the mini map share one top and one foot: the frames stretch to the week's last rule, and the two links (All library hours, Open in Google Maps) sit on one line under them. The hours and map columns use `display: contents` so their parts sit on the strip's own grid, via the `pf-visit-frames` mixin. The keyed strip's heading now reads "Hours and directions" instead of "Plan your visit". Why: the three columns ended at three different heights, and the panel gives hours and a map rather than help planning.
- **Hero banners: room under the search, and a tighter gap to the Quick Start.** No 3rem rise on the 44–64rem cover (about 1024px wide), so the row sits 52px under the search there instead of 4px, and the parallax's cross never reaches it. With motion on, the Quick Start's top padding subtracts the row's lift (`--pf-banner-drop` + `--pf-banner-travel`, both now set on the lead), so the gap seen under the level row is `--pf-hero-gap`, the gap above every index rule. At 1440 that's about 100px, down from about 204px. Reduced motion keeps the full gap. Why: the parallax lifted the row away from the section under it, and at 1024 it slid the banners under the search.
- **Hero banners: parallax that crosses at the centre.** Two scroll-driven parts on each banner link: a drift (the row rises by `--pf-banner-travel`, now only from 80rem, where the gap under the search has room) and a cross on the row's own view timeline (`contain` range). The cross keeps the first screen's stagger, levels the banners as the row's middle meets the screen's, and carries on until the stagger is mirrored. Why: the row used to settle level before it reached the middle, then stop.
- **Fix: hero spacing around the hours line.** The week timetable is now positioned absolutely (column 3 to the right edge, its foot on the intro's) instead of making the hero's top a grid. Why: in a grid the title, hours line and intro's margins stopped collapsing, so the gaps above and below the hours line about doubled.
- **Hero: this week's hours, and deeper banner parallax.** On the five-column cover (80rem and up) a seven-day timetable (today first, today marked by a highlight rule) takes columns 3–5, its foot on the intro's, in the search field's material, so the right side has a wide counterweight that reads at AA over any video frame (worst 5.89:1 across swatches). Filled by visit-hours.js from the same LibCal request; hidden narrower, or if LibCal fails. The banners' scroll travel is now a token, `--pf-banner-travel` (2.5rem, 4.5rem from 64rem, was 1.5rem), held under the gap below the search. Why: balance the composition without tying it to the changing videos, and give the parallax more depth.
- **Revert: hero title and intro as a spread.** Back to one flush-left
  column (title, hours, intro, search label and field on one axis). The
  intro in columns 4-5 sat on the photograph's subject, needed a visible
  wash patch, and split the page's one strong axis into two weak ones; the
  open video on the right is the cover's counterweight, not empty space.
- **Hero: a smaller two-line title, banners closer to the search.** On
  the full-screen cover the title, now on two lines, steps down to about
  72px at 1440 (76px at most; it was up to 96px). The room under the
  search is a quarter of the screen, kept between 13rem and 16rem, so the
  banners follow it 64-112px lower (about 230px before on a tall screen);
  the search now sits a little above the lower third, and on tall screens
  the extra room goes to the sky above the title. Why: the stacked title
  read top-heavy, with an empty band of video under the search.
- **Hero title on the intro's columns.** From 48rem of landing the title
  keeps to the intro's width (half the column; two of five from 80rem),
  its lines balanced, so "Special Collections" stands as "Special /
  Collections" over the hours, intro and search label: one column of
  type. A short title stays on one line. On the full-screen cover the
  fixed space above the title is now only a floor (3.5rem at most; it was
  up to 7.5rem), so the taller title still leaves the search on the lower
  third line (601px at 1440x900, 1280 and 1920). Why: the one-line title
  overhung the intro by about 200px.
- **Quick Start option names on two lines alike.** Each option's name is
  now two parts in includes/guide-request-paths.html.twig ("See it" / "in
  person", "Get a" / "digital copy", "Have an" / "expert help"); the
  hero's timetable breaks between them, so A, B and C all read as two
  lines on one baseline, where A alone sat on one. Elsewhere (phones, the
  full guide) the name stays one line.
- **Sign in as the intro's last sentence; TOC icon long-short-short.**
  The intro arrives in its field's wrapper (a clearfix div), which kept
  "Sign in to request materials" on a line of its own; the wrapper, its
  last paragraph and its clearfix now run inline, so the link follows the
  last sentence (wrapping whole to the next line when that one is full).
  The TOC icon's outline is now one item over two indented sub-items.
- **A 32px landing bar; an outline TOC icon; the map reveals only its
  drawing.** The landing's sub-header bar is 32px (no padding; the
  breadcrumb unchanged), its video and TOC buttons 32px squares at its
  top, still lined up with the logo and the header's last icon
  (--pf-bar-button). The TOC buttons (the floating one and the phone's)
  show an outline, two items and an indented sub-item with diamond
  bullets, instead of a bulleted list that read like the menu. On the
  hero cover the map block no longer fades in as a whole and its heading
  no longer slides in: only the map's drawing and labels appear as they
  draw (.is-tracing). Why: a slimmer bar under the header, an icon that
  says "contents", and a calmer map section.
- **Hero banners: even gaps two by two.** A banner's drop is now a
  relative shift (`top`), not a margin, so the right column's lower
  banners no longer make each row taller; every gap between rows is the
  gutter, as between the columns. The grid keeps the drop as room under
  its last row (--pf-banner-drop). Why: on smaller screens the left
  column's gap was the gutter plus the drop.
- **Hero banners: no row of bare picture under the scrim.** The banners'
  heights are fractional (460.78px at 1440), and rounding sometimes left
  a 1px row of picture below the scrim. The scrim now runs past every
  edge (2px at the foot), clipped by the banner, as the exhibition
  cover's wash does.
- **From the stacks: a stage that follows the lists; On view out of the
  hero.** The hero's On view (exhibition tiles under the map, index rule
  03) is gone. In From the stacks the large picture is now a stage
  (js/stacks-stage.js, new library `palefire/stacks-stage`): it shows the
  featured item's cover and crossfades to whichever item the pointer is
  over, or has keyboard focus, in its lists (an event's LibCal picture,
  passed by js/upcoming.js; a blog post's Image; the feature's cover); an
  item without a picture, or leaving the lists, brings the feature back.
  The featured item is now a list of its own, "Featured", between
  Upcoming and From the blog, styled like the posts (label, title, its
  intro in two lines). Blog posts there carry their Image. Why: the On
  view tiles repeated the banners, and one picture that answers the
  pointer reads as more alive than a fixed one.
- **Sign in in the intro; hours as label and sentence; banners deeper,
  with parallax, five columns to 44rem; dark header on light swatches;
  phone Quick Start folds.** Sign in leaves the bar for a link ending the
  hero intro ("Sign in to request materials" with an up-right arrow; it
  opens the request system in a new tab). The hours are the status as a
  label (dot and small capitals in a frame in the state's colour), then
  today's hours and the building in the text face; no "All hours" link.
  The banners are one row of five from 44rem of landing (titles and
  padding scale with it); narrower, the blog post is a line of text and
  the rest go two by two with the right column lower (4:5 on tablets). On
  the full-screen cover they rise 9rem into the video with a soft shadow
  (the band runs 1rem past the fold, their tops 8rem above it), and a
  scroll-driven parallax closes their offsets as the page scrolls (off
  with reduced motion). Light swatches get the header inverted: the
  swatch's heading colour as its glass, the page colour for its text and
  icons, the dropdowns to match. On phones the Quick Start folds to its
  first option under a fade, with "Show all steps" (js/palefire.js;
  focus inside unfolds it too). Why: sign-in belongs to requests, not the
  site; mono is for labels; the banners needed depth; the all-light frame
  glared.
- **The hero's bar as a clear caption strip.** On the hero cover the
  sub-header bar has no glass at the top of the page: the breadcrumb,
  Sign in and pause sit straight on the video (a firmer top wash under
  them, a halo of the page colour round each word), so the header is the
  page's one bar. Once the page scrolls and the bar sticks, it turns to
  glass (js/palefire.js marks `<html>` `.pf-bar-stuck`; a .3s fade).
  Reduce transparency keeps the glass. Measured against the pixels behind
  each word: 7:1 or more on Twilight, Cream, Sepia and Sage (Sign in was
  3.0:1 on Sepia before the firmer wash). Why: two near-equal glass bars
  read as two headers.
- **Hours line at AA over the video.** On the cover the hours line takes
  the text's full colour (today's hours and the building at 500) with a
  soft halo of the page colour. Measured against the pixels behind each
  word, the muted "Hill Memorial Library" fell to 3.3:1 on Twilight; now
  every word is 4.66:1 or more on Twilight, Cream and Sepia. Why: WCAG 2.2
  AA, 4.5:1 for small text.
- **Quick Start in even rows; Before you visit indented.** In the
  timetable every stop is at least two lines of name and a note tall, its
  diamond and words centred in it, with 1.5rem between, so the stops of A,
  B and C line up across the columns even where a name wraps; the line
  runs mark to mark. The option names share a bottom line, each letter on
  its name's last line. Before you visit keeps one hairline, under its
  heading, and indents its items 1.25rem on both sides with no rules
  between them, each after a small diamond in the highlight colour. Why:
  wrapped names pushed the stops out of line and the A/B/C headers read
  broken; the ruled list was busier than it needed to be.
- **Quick Start a step larger; Before you visit; taller banners.** The
  Quick Start's stop names take the paragraph size (18px wide) at 500,
  their notes 16px, the option names 20px (letters on their first line,
  names and stops wrapping evenly; on five columns each name keeps two
  lines' room so the stops start level). Under its intro, "Before you
  visit": a short list in hairline rows, built in from what the full guide
  states (Guest sign-in, appointments, staff bring materials, scan costs),
  or the Landing hero's optional `field_quick_guide_visit` (Text,
  formatted, long) instead. The banners are taller on the grids (3:5 on
  four columns, 1:2 on five), and on five the three high ones now align.
  Why: the steps read small beside the page's display type, the heading
  column had room for useful notes, and the banners' words needed more of
  the scrim.
- **Banner labels as pills; titles clamped.** The banners' labels are now
  the Collections tiles' black pill (white small capitals, gold on hover),
  and the blog and event labels drop their dates ("From the blog", "Next
  event"; the date, and the event's time, are read to screen readers).
  The blog post's category and the event's time and place lines are gone.
  Titles stop at three lines, so they always sit inside the scrim. Why:
  long titles ran out of the gradient, and the extra lines crowded the
  first two banners.
- **Five columns from 80rem; the latest blog post as the first banner.**
  From 80rem of landing (about 1440px) the hero's grid has five columns,
  four below: five banners mirror around the centre column (centre
  highest, outer pair a step down, second and fourth lowest); the Quick
  Start and On view headings span two columns and their content three;
  the hero intro and the map's text span two; the Visit panel is
  Instagram, hours over two, mini map over two. The Quick Start's mark
  track there is the diamond's own 2.75rem. The banners now start with
  the newest blog post (`_palefire_hero_blog_banner()`, from the hero's
  Blog categories: its Image, else its date set large), then the
  configured banners (up to five in all; the first configured one still
  turns into the next event). Four show on the 4-column grid; all swipe
  on phones. Blog posts gain `day`, `month` and `image_item`. Why: two-
  and three-column spans bring the headings close to thirds, the centre
  column balances the banners, and the blog post gets a picture-led place.
- **Hours under the title; At a glance out; next event in the first
  banner; reveals back, once.** Today's hours are now a caption under the
  hero's title (status dot, status, today's hours, the building, "All
  hours"; visit-hours.js fills it). At a glance is gone (template, styles,
  the hero's blog post lookup), and the index rules renumber 01-03. The
  first banner becomes the next event when the events feed has one
  (hero-event.js: its picture, cropped from the top under a deeper scrim,
  or its date set large; time and place under the title), and stays the
  first configured banner otherwise. The reveal animations are back
  (section-reveal and tile-reveal attached again), each playing once per
  page load; the request paths' reveal moves from scroll-driven CSS into
  section-reveal.js so it can be once too, and `$pf-paths-reveal` is gone.
  The banners' rise-in now brings the high ones down from above and the
  low ones up from below. Why: the hours belong with the title, the row of
  cells had become redundant, and replaying reveals on every scroll back
  was distracting.
- **Hero banners offset unevenly.** The banners' offsets are now 0, 3rem,
  1rem and 3.75rem (high, low, nearly high, lowest), as the Collections
  tiles' stagger was, in place of the even 3rem staircase. Why: the
  staircase read as mechanical; the uneven offsets look more alive.
- **Hero banners; plates out; a highlight for every swatch.** Up to four
  tall banners now sit under the search: the Landing hero's optional
  `field_hero_banners` (Content: Tile and Modular page; added in the UI),
  read by `_palefire_hero_banners()` through the same card builder and
  portrait covers as the Tile showcase. On the 4-column grid they straddle
  the cover's foot in a descending stagger (3rem steps) and rise in on
  load, left to right (CSS only; off with reduced motion); narrower, a row
  to swipe. Their words are white over a scrim, not gold. At a glance's
  plates are gone (template, hero-event.js, styles), and with them the
  Sign in picture field's code and the blog posts' plate keys. New
  `--pf-g-highlight` per swatch (its `highlight` key, else `title`, else
  `accent`) colours the page title, the index numbers and the Quick Start
  letters, so every swatch marks them as Twilight does (Cream: LSU
  purple). Why: the banners lead from the cover into the page with real
  collection imagery, and only Twilight and Material had a title colour.
- **Hero cover in thirds.** On the 4-column grid the video fills the
  first screen less a 10rem strip (--pf-cover-peek) and covers only the
  title, intro and search; the search sits on the screen's lower third
  line, the title and intro rising from it, and index rule 01 and the top
  of At a glance's plates show in the strip on the plain page. The title
  is larger there (up to 6rem), and the band's bottom fade is gentler so
  the video shows under the search. Narrower layouts are unchanged. Why:
  the plates on the moving video read as busy; this gives the first
  screen one calm picture, composed like an exhibition cover.
- **Header always compact.** Every Palefire page renders the header
  compact (`pf-header--compact`, `data-pf-compact="always"` in
  page.html.twig), and js/palefire.js leaves such a header alone: no
  growing or shrinking on scroll. Rules that size to the full header
  (--pf-header-full) fall back to the compact height. Remove the attribute
  and class to bring the growing header back. Why: about 38px more of the
  first screen on every page, and no jump on the first scroll.
- **Sign in plate picture.** At a glance's Sign in plate can show a
  picture: the Landing hero's optional `field_account_image` (Media: Image
  or a plain Image, one value; added in the UI like the Quick Guide
  fields), read by `_palefire_account_image()`. The picture fills the
  plate under a scrim (as on the exhibition tiles) with the words in
  white; without it the plate stays a card. Why: a photo of the request
  system reads better beside the other pictures than a bare card.
- **At a glance plates.** On the 4-column grid each At a glance cell now
  starts with a 16:9 plate on its column: a Sign in card (the request
  system, as the bar's link), the next event's LibCal picture (cropped
  from the top, as they are often flyers) or its date set large
  (js/hero-event.js), the first exhibition's picture, and the blog post's
  Image (field_image, when the article has one) or its date. The cards are
  opaque so the video doesn't show through their words; picture plates
  repeat their cell's link and are out of the tab order. Blog posts gain
  `day`, `month` and `image` (_palefire_blog_posts()). Narrower layouts
  show no plates (their images never load). The cover's space above the
  title is 3.5rem on this grid so the row fits a 1440x900 first screen.
  Why: the area under the search read as empty; the video already shows
  Hill, so the first plate is the account instead.
- **Quick Start intro three sixteenths wide.** In the timetable the
  Quick Guide intro spans 3 of its column's 4 sixteenths, leaving one
  empty and a gutter before column A. Written for the shortened intro (a
  sentence or two; about four lines). Why: at the full column it crowded
  A's letter and diamonds.
- **Index rules with right-hand links.** Each index rule now ends in a
  link on the column's right edge, in the rule's small capitals with an
  arrow: 01 "All hours" (LibCal, as the Visit panel's), 02 "Full guide"
  (moved from the end of the Quick Guide intro; the Link field's text and
  URL still apply), 03 "Directions" (Google Maps, new tab) and 04 "All
  exhibitions" (/exhibitions, the main menu's page). The rules are built
  by an `index_rule` macro in includes/landing.html.twig; only their number
  and name are hidden from screen readers now, so the links are reachable,
  each with a full name ("Directions to Hill Memorial Library (opens in a
  new tab)"). Why: left-aligned text left the right margin ragged; a mark
  at each rule's right end balances the number at its left.
- **Reveal animations off; Quick Start marks on their own 16th.** The
  section-reveal (headings, Events carousel) and tile-reveal (Collections
  cards) libraries are no longer attached (the templates note how to put
  them back), and the request paths' scroll-in is behind a new
  `$pf-paths-reveal: false` (_variables.scss). In the Quick Start
  timetable each option column now puts its letter and diamonds centred
  in its first 16-column track and starts the words on the second, a
  gutter later; the diamonds are 2.75rem across (or the track's width),
  their icons back to 1rem. Why: the layout is being settled without
  motion in the way, and the marks crowded the words and their icons.
- **Hero: the video cover fills the first screen on desktop.** On the
  4-column grid the cover's video spans the title, search, index rule 01
  and At a glance and is at least the window's height under the header;
  its first row is the flexible one, so the room opens above the title
  and title, search and At a glance sit at the screen's foot, with the
  band's solid foot under them (02 keeps its gap). The wash's solid foot
  runs a little higher to sit under At a glance. Narrower layouts keep the
  band ending under the search: stacked, At a glance would cover the band
  on thinly washed video. Why: the first screen read as bare below a
  short band.
- **Landing bar icons on the header's edges.** The header now publishes
  its side padding and utility-icon cell (--pf-header-gutter,
  --pf-utility-cell, _header.scss, the same breakpoints and compact size
  as the header). From lg, the floating TOC button's icon starts on the
  logo's left edge, and the video button centres under the last header
  icon (the settings gear), with Sign in paired before it; both follow the
  header as it compacts. Why: the bar's corner buttons sat 6px from the
  screen's edges, off the header's lines.
- **Hero: one 16-column grid for 01 to 04.** The landing's content column
  is a 16-column grid with one gutter (--pf-grid-gutter, up to 2rem), the
  main blocks spanning 4, so its four columns are equal and every fourth
  line is theirs. Every layout switch now reads the landing's own width
  (pf-cover: 2 columns from 30rem, 4 from 64rem) instead of the lead's
  or the window's. On it: the intro and the map's text span 8 (half); the
  Visit panel puts Instagram, hours and the mini map in columns 1, 2-3
  and 4 (two even columns from 48rem); On view's heading takes column 1
  and its tiles columns 2-4 with the gutter between, like the Quick
  Start. Why: those blocks had their own widths (46ch, 62ch, 1fr/1.25fr,
  thirds) and none lined up with the columns.
- **Hero: columns start on one edge; bar buttons without borders.** The
  Quick Start's letters and diamonds now start on the column edges (no
  longer hanging in the gutter), so every column's first ink meets At a
  glance's above, and the intro keeps the full gutter before column A
  (--pf-grid-gutter back to at most 2.5rem). Sign in, the video button and
  the floating TOC button over the bar lose their outline, fill and
  shadow, with a faint fill on hover and focus. Why: the hanging marks
  read as columns off the grid and crowded the intro; the outlines were
  noise on the bar's glass.
- **Hero: one rhythm, a shorter type scale, text on the columns, no
  suggestions.** The search suggestions are gone (template, the
  `_palefire_search_suggestions()` helper and their styles), and the
  search runs the column's full width, its edges on the rules'. Every
  index rule has the same space above it (--pf-hero-gap, counted from the
  search for 01) and under it (--pf-hero-rule-gap). Paragraphs share one
  size (--pf-hero-text); the Quick Start's names and stop labels take At
  a glance's 16px at 500, and its letters the h2 size. In the timetable
  the words start on the column edges, with the letters and diamonds
  hanging in a wider gutter (--pf-grid-gutter up to 3rem). The title is
  tracked at -.02em (was -.035em) with its first letter's side bearing
  pulled back. Why: uneven gaps, too many sizes, text off the columns,
  and a pinched title.
- **Quick Start as a timetable; diamond stops; 03 and 04 rules.** On the
  hero's four columns the Quick Guide's heading and intro take the first
  and options A, B and C one column each, their stops running down
  (narrower layouts keep the rows). "See the full guide" is now an inline
  link ending the intro, not a button. The index rules set the name right
  after its number at every width, and "03 Visit Hill" and "04 On view"
  head the map and On view (On view's own hairline is gone). The request
  paths' stops, here and in the full guide, are diamonds instead of
  circles, icons upright. Why: the three full rows were tall and
  repetitive, the labels sat far from their numbers, and circles were the
  one soft shape in a hard-edged theme.
- **Hero: indexed label rules, Quick Start stops on the four columns.** A
  hairline with a mono number and name ("01 At a glance", "02 Start your
  research") now heads At a glance and the Quick Guide, the number in
  gold (one of the small gold marks) and the name at the second column's
  edge; the star rule is gone, and At a glance's first row drops its own
  rule under 01. From 52rem each Quick Start option's name sits above its
  stops, and the four stops sit in At a glance's four columns with the
  same gutter. Why: the separators said "new section" three different
  ways, and the tracks were the last block off the grid.
- **Hero: search and Quick Guide rule on the four-column grid.** At a
  glance's gutter is now the hero's (--pf-grid-gutter). Where the row is
  four across, the search spans three columns, ending on On view's right
  edge instead of a fixed 58rem, and the star rule's lines stop on the
  middle gutter's edges with the star centred in it. Why: both sat off
  the page grid.
- **Quick Start as tracks, icons back; suggestions as labels; calmer map
  tooltip.** The stops' discs show their duotone icons again. From 52rem
  each option is one track: the line leaves the option's name and runs
  through its stops, which sit flush left, so the rows need no rules
  between them (narrow layouts keep them). The suggested searches are set
  in the search label's small spaced capitals, so label, field and
  suggestions read as one block. The map's summary now takes the 80%
  paragraph tone (it set its own colour). The map tooltip is square with a
  hairline edge and a softer shadow, with a mono floor line, the room in
  the text face (not gold capitals) and a plain-words link. Why: the
  table had too many lines, the suggestions looked out of place, and the
  tooltip didn't match the page.
- **Hero bar: glass blurs again, TOC button in line, Sign in paired with
  pause.** The cover landing drops the clip-path the old fixed video
  needed; it made the landing a backdrop root, so the sticky bar stopped
  blurring the text scrolling under it. The floating TOC button takes the
  bar's .375rem top in CSS instead of the title nudge, which is measured
  only when the page loads at the top (reloaded part-way down, it sat a
  row low). Sign in now sits .375rem from the pause button, the same as
  pause from the screen edge, instead of an odd 26px. Why: the text behind
  the bar was confusing, and the corner buttons didn't line up.
- **Homepage: At a glance as ruled cells, suggestions as plain links, Quick
  Start as a ruled table.** The Today strip drops its vertical rules for a
  hairline over each cell with gutters between, two by two until the lead is
  75rem wide (four across at 64rem cut the titles off). The floating "Try"
  label is gone (screen readers still hear "Suggested searches"); the
  suggestions are text links on the field's left edge that underline on
  hover. The Quick Start options sit between hairlines, A to C, and the stop
  labels lose their dotted underline (the "how" panels still open on click
  and underline on hover; their line height now matches the plain labels).
  Why: the strip's 1-2-1 rules, the label and the dots broke the grid, and
  the options looked like they floated.
- **Homepage: paragraphs a step softer than their headings.** The map's
  description and the Quick Guide intro are set at 80% of the text colour
  (bold words at full strength), so each heading leads by tone as well as
  size: about 9.9:1 on Twilight, 8.2:1 on Cream. The intro on the video
  keeps full strength. Why: heading and paragraph read as one block.
- **Request paths, calmer** (the homepage's Quick Guide and the full guide,
  `includes/guide-request-paths.html.twig`). Each stop's disc holds its
  step number (1–4) instead of an icon; the image and info badges after
  the labels are gone (the dotted underline still marks a label that opens
  a panel); the options are set apart by a hairline (stacked) or the
  spacing (in a row) instead of "or". On the homepage the A/B/C labels lose
  their boxes and arrows and sit on the line of discs. Why: the guide was
  the busiest typography on the page.
- **Homepage type pass.** One section heading for the page (the map, the
  Quick Guide, On view and the sections after the landing: one size,
  weight and tracking, in the text colour); gold kept for the page title,
  the Search button and small marks. One left edge: every section on the
  page keeps to the landing's centred 1280px column (a registered length
  per section, `--pf-hero-col-pad`), and On view and the map's Visit panel
  lose their boxes for a hairline. Buttons and links (Search, Sign in, See
  full guide, the Visit links) in the text face; mono stays for labels.
  The Visit panel in the map is "Plan your visit" (the map already names
  the building). Hours read "9 AM–5 PM" everywhere (a no-break space before
  AM/PM, a closed-up en dash; `visit-hours.js`) in tabular figures. At a
  glance's On view line names the first exhibition and counts the rest.
  Balanced headings, no lone last words, paragraphs about 62 characters.
  Why: a print-style review of the page.
- **Hero: the video becomes a washed cover; no curtain, no snap.** The
  background video moves from behind the whole landing into a band behind
  the title, intro and search, washed into the page colour as on exhibition
  title pages, and fades into the wash as it scrolls up (scroll-driven,
  off with reduced motion). The glass panel, the full-screen lead, the snap
  to the map (`landing-snap.js` isn't attached on the hero) and the
  curtain reveal are gone from the hero, so the page scrolls on through
  the map in one flow; the curtain's and full-screen panel's CSS is
  removed. The clip plays only while the band is on screen. Other landings
  keep their lead, video and snap. Why: the curtain split the homepage
  into two pages.
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
