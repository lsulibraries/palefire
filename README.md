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
- [Reading colors and Settings](#reading-colors-and-settings)
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
| `palefire_edit_form_tidy.php` | Shorter edit forms: Paragraphs fields open collapsed (Edit, Duplicate, Add above, drag to reorder), details groups closed, the Auto / Light / Dark section theme hidden, Layout and section field labels and help rewritten. Dry run unless `-- apply=1`; the dry run also lists fields it leaves alone |
| `palefire_landing_hero_setup.php` | `landing_hero` paragraph type |
| `palefire_landing_hero_tiles_setup.php` | Hero tile type, Tiles and Blog categories on Landing hero |
| `palefire_landing_department_setup.php` | `landing_department` type (run after the hero tiles script) |
| `palefire_tile_showcase_setup.php` | `tile_showcase` type, its image styles and responsive image styles |
| `palefire_landing_stacks_setup.php` | `landing_stacks` type (From the stacks): Featured item, Blog categories, LibCal calendar and campus (run after the tile showcase and hero tiles scripts) |
| `palefire_guide_setup.php` | Guide content model: Resource type, guide paragraph types, guide fields (incl. Guide editors) |
| `palefire_louisiana_newspapers_setup.php` | `parish_map`, `parish_entry`, `family_trees` paragraph types |
| `palefire_guide_wysiwyg.php` | Moves existing guides' text to the WYSIWYG format (dry run unless `-- apply=1`) |
| `palefire_exhibit_setup.php` | Exhibition content model: `exhibition` mode, title page fields (incl. the program pages' label, key facts and main button), `exhibit_*` paragraph types, `exhibit_*` image styles, the Sources text style (after the guide setup) |
| `palefire_exhibit_reading_setup.php` | Adds Exhibition: Reading (`exhibit_reading`), a book or article typeset as a cover: Kind, author, title, publisher or journal, details and `field_figure_link`, and allows it in the Image group. Run after `palefire_exhibit_setup.php` and after `field_figure_link` is on Exhibition: Image |
| `palefire_exhibit_aves_lead_captions.php` | Fills the 9 draft AVES lead captions on the existing page (empty ones only; stops on a published page unless `-- force=1`) |
| `palefire_exhibit_aves_update.php` | Brings the live AVES page up to `data/aves.json`: Capital Case names, "Main Library", the 16 images from `Section 4 (additions)`, catalogue lead captions (`-- images="<AVES exhibition 4K folder>"`). Keeps editors' own changes |
| `palefire_exhibit_aves_typos.php` | Corrects 25 typos in the live AVES page (missing spaces and brackets in names, hyphen and dash spacing, Vieillot's dates, Weimar, Chubb), the same as in `data/aves.json`. Exact replacements only; lists any it can't find |
| `palefire_exhibit_aves.php` | Builds AVES as an unpublished exhibition (`-- images="<AVES exhibition 4K folder>" path=/v2/exhibitions/aves`), importing the 4K images into Media |
| `palefire_exhibit_occult.php` | Builds Occult Science and Philosophy in the Renaissance as an unpublished exhibition (`-- images="<Occult Science 4K folder>" path=/v2/exhibitions/occult`), importing its 40 images into Media |
| `palefire_exhibit_mossville.php` | Builds the Mossville History Project oral history exhibition as an unpublished exhibition (`-- images=<2560 px masters> audio=<M4A clips> path=/v2/exhibitions/mossville`), importing its 60 photos into Media and its 70 clips as files. Text in `data/mossville.json` |
| `palefire_exhibit_audubon.php` | Builds John James Audubon in Louisiana as an unpublished exhibition (`-- images=<Audubon 4K folder> path=/v2/exhibitions/audubon`), importing its 93 images into Media |
| `palefire_exhibit_redesign.php` | Builds "A New Look for LSU Libraries", the site redesign before and after, as an unpublished exhibition (`-- images=<Redesign screenshots folder> path=/v2/exhibitions/redesign`); `edition=staff` builds the staff page, which adds four staff-only galleries under its own title (`path=/v2/exhibitions/redesign-staff`, access restricted in the UI). Both share the 20 screenshots' media items |
| `palefire_exhibit_replace_image.php` | Swaps an Image media item's file for a new one (`-- media="<media name>" file=<path>`), keeping the item, its alt text and every figure using it. For a better scan found after an exhibition was built |
| `palefire_exhibit_lead_mirror.php` | "Mirror lead image" (`field_exhibit_lead_mirror`, boolean) on Exhibition: Gallery, its checkbox under "Show whole image" (and in that field's group) |
| `palefire_exhibit_audubon_wall.php` | Sets the already-built Audubon page's Elephant Folio, Octavo Edition and Drawings and Proofs to Display "wall", their sheet sizes, the pairs' "Drawing" / "Proof" captions and the drawings' alignments (empty ones only). Run `palefire_exhibit_setup.php` first |
| `palefire_exhibit_audubon_readings.php` | Turns the Audubon page's Suggested Reading list into readings: Image groups "Selected works" (18 books, 5 articles) and "Fiction" (2 books), linked to their catalog records or articles, and cuts the list from the text. `-- dry-run=1` saves nothing. Run `palefire_exhibit_reading_setup.php` first. Saves a new revision; stops if the gallery already has readings |
| `palefire_section_theme_check.php` | Read-only: lists theme options and each section's saved theme, e.g. `… /special` |
| `palefire_guide_demo.php`, `_research_help.php`, `_special_collections.php`, `_louisiana_newspapers.php` | Each builds one guide as an unpublished page (`-- path=/v2/guides/…`). One-time starting points: they stop if a page with the same title exists and never update one |
| `palefire_guide_parking.php` | Builds both visitor parking guides as unpublished pages, Main Library and Hill Memorial Library (`/v2/guides/parking-main-library`, `/v2/guides/parking-hill-memorial-library`; `-- only=main\|hill`, `main_path=`, `hill_path=`). Skips a guide whose title exists |
| `palefire_guide_special_collections_glossary.php` | Builds the Special Collections Glossary as an unpublished page (`/v2/guides/special-collections-glossary`; `-- path=`), linking to How to Use Special Collections. Stops if the title exists |
| `palefire_guide_faqs_section.php` | Adds "Frequently asked questions" to How to Use Special Collections, before "Get help" (`-- before=`): every Special Collections FAQ, most asked first, through a `[special-collections-faqs]` marker. Content only. Dry run unless `-- apply=1`; stops if the section exists |
| `palefire_special_programs.php` | Builds the Special Collections program pages as unpublished exhibitions: Teaching with Special Collections, Research Grants, Faculty Fellowship and Summer Project (`-- prefix=/v2/special/programs` gives `…/teaching`, `…/research-grants`, `…/faculty-fellowship`, `…/summer-project`). Title page with key facts and an Apply button; galleries for the program, its people, how to apply and questions. Drafted from LSU's announcements, with `[Editor: …]` notes for current terms. No title images yet. Skips a page whose title exists (run `palefire_exhibit_setup.php` first) |
| `palefire_special_programs_section.php` | Adds "Time in the Archive" (Teaching & Research) to `/v2/special` after "Off the Walls, Online": a Ledger Tile showcase of the four program pages, and the `ledger` Layout value if missing. Dry run unless `-- apply=1`; stops if a program page is unpublished (`force=1` overrides), the section exists, or the page has a pending draft |
| `palefire_ask_section.php` | Adds "Ask Special Collections" to `/v2/special` before the stacks (`-- after=<title>` to place it): a Tile showcase in the Ask layout (Special Collections FAQs beside a question form), and the `ask` Layout value if missing. The dry run lists the FAQ tiles it would show. Dry run unless `-- apply=1`; stops if the section exists or the page has a pending draft |
| `palefire_staff_setup.php` | Staff directory and profiles: the View `palefire_staff` (`/v2/staff`, `/v2/staff/%`; the theme prints both, `src/StaffDirectory.php`), the "Who to ask" section (`staff_contacts`, rows `staff_contact`: Topic + Person) and the `pf_staff_thumb` / `pf_staff_portrait` image styles (`-- staff=<type>`, default `staff`). Run after the tile showcase scripts |
| `palefire_staff_contacts_section.php` | Adds "Who to ask" to `/v2/special` after "Ask Special Collections" (`-- after=`): six topics, each person found by Job title (a topic with no single match is listed and left out), linked to `/v2/staff?department=special-collections`. Dry run unless `-- apply=1`; stops if the section exists or the page has a pending draft |
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

- From lg, guides keep the TOC as a sticky sidebar; every other mode shows it as
  the **reading rail** (below). Below lg a list-icon button opens it as an
  offcanvas (`<html data-pf-panel="toc">` gives it the drawers' backdrop).
- Its visible title is "Table of contents", a plain label that names the
  `<nav>`.
- **Reading rail** (lg and up, not on guides, `_toc-rail.scss`): the same
  `<nav>` as a 3px line at the page edge, one segment per section, as long as
  the section, filled as far as the activation line has read into it. The
  column closes and backgrounds run to the edge. Hover, focus or a first tap
  opens the **index panel** beside it: every section on an even row, a visual
  copy of the links (`aria-hidden`; the rail's links stay the nav), where a
  segment and its row light together. Esc or following a link closes it. With
  the panel closed, the current name shows by itself only while its heading rises from the activation line to 45% of
  the screen (`PEEK_END`), fading as it goes. When the segments can't
  all be 24px tall, it switches to even ticks (`.pf-toc--ticks`). It is set by
  `.pf-modular--toc-rail` (`page.html.twig`), which is left off when the
  sidebar region holds other blocks: the sidebar stays then.
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
  a band behind the title, intro and search, washed into the page color
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
`index` is a numbered list with no pictures. `masonry` has a Grid / List
switch (its Masonry view was removed 2026-10-30) (`js/tile-views.js`, remembered per browser); Grid is the
default and shows the label under the picture. With one to four cards, five
across, Grid's first card leads, wide enough to fill the row (so put the
guide to start with first); five or more stay even. Each card: the title (h3), the
cover (a Tile's Tile Cover; a Modular page's Image cover or landing poster)
through the
`tile_showcase_portrait`/`_landscape` responsive styles, its Category terms as
the label, and its link. Layouts use container queries; the staggered row is a
swipeable strip under 60rem. `js/tile-reveal.js` animates cards in on scroll
(nothing hidden without JS or with reduced motion). A scrim keeps white titles
at 4.5:1 on any image. A card with no cover draws its page instead, as a
desktop shows it (the "On this page" rail, then the title, intro and first
sections; `_palefire_page_outline()`), lying at an angle and blurred, in the
reading swatch's colors.

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
Swiss style, theme CSS only (colors through its `--ldc-*` properties, every
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
| `exhibit_gallery` (Image group) | As in exhibitions (below), once ticked in `field_guide_blocks` by hand. In a guide it brings its own lightbox (`palefire/guide-gallery`) |
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

- Styles in `_guide.scss`, colors from the reading-color tokens (`--pf-g-*`)
  and `--bs-*`; no literal colors.
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
`includes/exhibit-cover.html.twig`): `field_exhibit_title_style` (Serif,
Playfair Display, the default; or Sans), `field_exhibit_subtitle`,
`field_exhibit_cover` (Media: Image, decorative), `field_exhibit_credit`,
`field_exhibit_prompt` (default "Scroll through every gallery in order, or
choose one from the table of contents."), and `field_intro` if filled. Optional tiles under the credit:
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
| `exhibit_figure` (Image) | `field_figure_image`, `field_figure_caption`, `field_figure_layout`, `field_figure_align`, `field_figure_link` (optional) | Layouts: beside the text (image first / second), centred, full width (parallax). Opens the lightbox; with a Link, links there instead (another site in a new tab), an arrow in the corner, and in an image group shows as a book cover (2:3, uncropped). Alignment ("k rot tx ty": scale, degrees, left and top in proof widths) lays a drawing over the proof after it in a wall's comparison |
| `exhibit_gallery` (Image group) | Optional heading, `field_gallery_items` (figures), group caption | Grid; each figure keeps its caption. On a wall, a group of two is compared (slider); of three or more, a manuscript's pages, turned one at a time (tile tagged "4 pages") |
| `exhibit_reading` (Reading) | `field_reading_kind`, `field_reading_author`, `field_reading_title`, `field_reading_source`, `field_reading_details`, `field_figure_link` | A book or article in a reading list, typeset as a cover with no image: a book in one of eight cloth colors (from its title), an article as a journal offprint. Goes in an Image group; the citation is the caption and names the link |
| `exhibit_citation` (Catalogue record) | `field_block_body` | Set like a museum label |
| `exhibit_audio` (Audio) | Title, `field_audio_file`, speaker/date, `field_audio_transcript` (required) | Oral history; custom player, transcript in a `<details>`. Inside an entry it prints no heading: the entry's `<h3>` labels it |

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
- Openings on a full-screen photograph (Cover, and a wall's lead), with
  motion and scroll timelines, at every width: the photograph never moves
  on screen. Below lg nothing is held: the wash rises from the foot and
  fades in while the gallery comes up, and each part of the text fades in
  as it scrolls up past the screen's foot (its own `view()`). From lg:
  It fades in where it rests while its gallery comes up, holds under the
  header, and fades out in place at the area's foot. Once it fills the
  screen the text holds at its foot for 90svh (`--pf-reveal`) while the
  side wash fades in slowly and slides in from half its width to the left
  (over 64% of a screen), then the title, the
  facts or links, and the opening text rise in one after another, scrubbed
  by the scroll; then the text scrolls on over the still photograph. A
  Cover whose text runs past the screen (`.is-long`) isn't held: it starts
  lower (56svh) and fades in as it scrolls up. From lg the foot has only
  the title page's light fade, except on openings with no text. Links to a
  gallery land at the reveal's end.
  Not for "Full screen, then pan", which has its own pinned sequence.
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
  up over it on the page color: the hero then spans the whole gallery
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
  caption), the pair's captions ("Drawing · Proof") or an audio clip's
  speaker (its credit before ", interviewed"; on its own line, wrapping,
  `.pf-exhibit-wall__tag--speaker`); a pair's second image
  crossfades in on hover. A wall of clips counts "7 clips" in its facts. "Plate / A–Z" sorts when two or more captions give
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
  left on the page-color wash (from the foot below lg, from the left from
  lg, the text kept in its dense part; a wall with no intro, only the title
  and facts, gets from lg only a soft rise from the foot, solid to 10% of
  the image's height and gone by 46%, so the photograph shows across the
  rest; its number and facts take the heading color; with scroll
  timelines the photograph stays pinned under the header while the page
  slides up over it, the wash carried up with the title (on the content,
  not the frame) and the wall on the page color, held at full strength
  for a quarter of a screen and gone by 70%); "Read more" grows the area down,
  never the column wider, and the image and its wash stay on screen
  (`.pf-exhibit-wall-hero__frame`, sticky) while the opened text scrolls
  over them; below lg a veil of the page color comes over the image while
  the intro is open. Folded, the intro shows four lines, or from lg over a
  lead as many lines as fill about 78% of the image's screen (measured,
  again on resize). No pan or band, whatever the Opening says.
  Without a lead, from lg the title and facts and the gallery's own text sit side by side as
  wall text, the wall full width below; text longer than four lines folds
  with "Read more" / "Show less" (`.pf-exhibit-wall__text`), so the first
  row of plates is on the first screen.
- **Object view** (a wall's tile, a link to its entry from the TOC or the
  text, the address `#anchor`, Back): a full-screen `<dialog>` in the page's
  reading colors. The image grows out of its tile (`view-transition-name:
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
  An entry with an audio clip: the panel's copy of the player is set up
  again (labelled by the view's title) and the clip stops when the view
  steps on or closes. No frame round the clip there: the transcript is the
  panel's text, under a "Transcript" label in place of the `<details>`,
  and the player pins under the panel's top row on the page color
  (`--pf-viewer-top-h`, measured) while it scrolls by. Behind its image, the tile's
  photo blurred (48px, 70% saturation) at 35% over the reading color
  (`.pf-exhibit-wash`, `setWash()`), fading in after the image and
  crossfading as the view steps, hidden while zoomed; never behind objects,
  whose colors a tinted ground would change.
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
  theirs is the nearest fit. The Virginian Rail has no larger source and stays a 2021 crop.
- **Manuscripts** (`Audubon 4K/manuscripts`, 9 JPEGs from the Special
  Collections TIFFs in `_reference/manuscripts`: 5188 Hurricane, 5189 Bear,
  5190 Children's Warbler; long edge 3,840px, quality 88, metadata
  stripped). Every page is shown whole: the Hurricane's two-page spread is
  cut at the fold (x = 6,240 of 12,577), and its fourth page is the blank
  back of the leaf. Each entry ends with a transcription made from the scans
  (struck words in `<s>`, interlinear additions in ⟨ ⟩, [?] for uncertain
  readings, a heading per page); it is a draft for a curator to proofread.
  `palefire_exhibit_audubon_manuscripts.php` applies them to the built page. Leads: the Brown Pelican (folio,
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

## Reading colors and Settings

Settings > **Reading colors** sets page and text colors for the whole site.
Defined once in `$pf-reading-themes` (`_reading-themes.scss`); each swatch
writes only `--pf-g-*` tokens on `<html>`, and Bootstrap, the chrome, the
landing, the map and the calendar read those.

- **Twilight** is the default (`$pf-reading-default`, `DEFAULT_THEME`):
  charcoal `#272429`, text `#edebee`, headings and accent pale gold `#f2dc9b`,
  hovers to lilac `#d4c9dc` / cream gold `#fbf1d6`. `html.html.twig` puts it on
  `<html>` before any script runs; the default is never saved.
- **Others:** Material, Cocoa, Forest, Dusk, Night, Midnight, Solarized (dark),
  Cream, Soft cream, Lavender, Sepia, Sage (light). The first four show, the
  rest behind "More colors". Every swatch keeps text, muted text and links at
  4.5:1 or more, including on glass over a video.
- Optional swatch keys: `title`, `strong`, `em` (multicolor, as Material),
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
| Display | High contrast (pushes the swatch's colors apart, 8.3:1+) | `.pf-a11y-contrast` |
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
  for the Hours clock, from Google Fonts, non-blocking. Exhibition cover titles
  use Playfair Display 500, self-hosted in `fonts/playfair-display/` (Latin,
  23 KB, SIL OFL), preloaded only on exhibition pages.
- **Desktop dropdowns:** links fade in in sequence and their underline draws in
  and out (`$pf-dropdown-stagger`, `$pf-dropdown-fade`).
- **Below lg:** logo | Search | Menu. The menu is a full-screen offcanvas
  (`#pf-primary-menu`, the same element as the desktop menu) with quick-link
  tiles, text size and reading colors (copies of the Settings tiles,
  `initMenuSetting`), and sections as disclosure toggles. Quick-link drawers
  open over the menu (`openOverMenu`); × goes back to the menu.
- **Utility drawers** (`includes/utility-panels.html.twig`, outside the header
  and `#swup`): Bootstrap offcanvas from the left in the reading colors'
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
  the section's line color), unless a control's boundary needs 3:1 (WCAG
  1.4.11). High contrast sets `--pf-hairline-strength` to 100%.
- **Hover timing:** color in over `$pf-hover-in` (.2s), out over
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
  color and over a black or white video frame.

## Changelog

Newest first. One line per change; the commit has the details. Entries before
2026-10-21 are summarised by day: for the full write-ups, see this file in git
history (`git show fa05a94:README.md`).

### 2026-10-31

- **Written at Hill: no flicker moving between books.** Crossing the gap between two books left nothing hovered for a moment, so the whole shelf flashed bright and dimmed again. Covers now dim at once but wait 300ms before brightening again, and the book the pointer lands on brightens at once. With reduced motion the changes are instant, keeping the wait. Measured each frame in headless Edge at 1440, book 3 to book 4: with 50ms or 250ms in the gap the other covers stay at 60% throughout; only after a 600ms pause in the gap does the shelf start to brighten.

- **Written at Hill: no blur on hover.** Hovering or focusing a book no longer blurs the rest of the shelf (the depth of field); it was too distracting. The other covers still dim to 60%, and that now applies with reduced motion too, instantly (the cue isn't motion). The book still turns toward the reader and lifts, and its title takes the accent.

- **Written at Hill: the shelf keeps to the gutters, under frosted glass, with its arrows by the lede.** The shelf ran to the window's edges and under the reading rail, and its arrows sat below the books, far from the section's text. It now scrolls inside the section's gutters (0.5rem past them, so a turned book's near edge and a focus ring aren't cut off). The books slide under a pane of frosted dark glass (6px blur, solid section color at the outer edge, fading inward) at whichever side has more books: `js/book-shelf.js` keeps `data-pf-at` (start, middle, end or none) on the new `.pf-tiles__shelf`. `scroll-padding-inline` is the glass's width, so a snapped or keyboard-focused book always stops clear of it. Previous / Next now come before the shelf in the markup: from a 60rem-wide section they sit on the right, level with the lede's last line; narrower, right-aligned just above the books. Phone cards are 45% of the shelf (were 40%), so "Reconstruction" no longer breaks mid-word at 320, and titles hyphenate as a fallback. Checked with the live page's markup, local CSS/JS and all 33 covers at 320, 768, 1024 and 1440: no horizontal scroll. A focused book's ring is never under the glass (at most 1px of the 3px ring is clipped at its top) and the arrows are never covered. At 320, the floating "On this page" button covers only the corner of a focused book at the very bottom of the screen (WCAG 2.4.11 allows partial).
- **Building map: hover tooltips close when scrolling leaves them behind.** Scrolling moves the page under a still pointer, and browsers don't reliably fire `pointerleave` until the pointer moves, so a hover tooltip could stay open over the sections below. `building-map.js` now keeps the pointer's last position and, on scroll (once a frame), closes a hover tooltip, or cancels its wait, when what's under that position is no longer a room, its name, a list entry or the tooltip. Tooltips opened by keyboard focus or a tap, and pinned ones, are left alone. Not tested in a browser (the live JS is aggregated).
- **Ask form: hovered fields take focus's faint wash.** Hovering a field now fills it with the same 5% wash of the text color that focus shows, alongside the rule's existing step up to the headings' color (`_ask.scss`); the gold rule stays focus's alone, and hovering a focused field changes nothing. The wash fades in and out with the existing hover timing. Why: the bare underline gave little sign of the box a click would land in.
- **Written at Hill: the guide's 33 books on one shelf, drawn as books.** Each cover is now a book turned a little to the left, so its page edges show (fine paper lines, shaded toward the back), as deep as its page count: Pages (`field_pub_pages`, new) sets `--pf-book-depth` (`_palefire_publications()`: about 0.06mm a page plus 3mm of boards on a 6in book, 6-30% of the cover's width; 300 pages without a count). Hover or keyboard focus turns the book toward the reader and lifts it, and the other covers blur 2px and fade (a depth of field; their text stays sharp). With reduced motion the books stay still and nothing blurs. The section showed 5 of the research guide's 34 entries; it now holds all 33 books (not the PBS film), newest first, on one shelf that scrolls sideways at every width, with Previous / Next buttons (`js/book-shelf.js`, attached only by the Covers layout) shown while it overflows, disabled at its ends. `palefire_special_publications_shelf.php` adds the field and the books, with page counts and catalog links from the LSU Libraries catalog, and fixes Lost Causes, which linked to record 7633160 (The New Era in American Mathematics; the catalog has no Lost Causes, so it and three others link to a catalog search). It also changes the lede's "Recent books" (the list now goes back to 2001). `palefire_special_publication_covers.php` lists the 25 new covers (31 of 33 books have one; Old South Baton Rouge keeps a typeset jacket). Each shelf item is positioned, so the visually hidden subtitles no longer widen the page. Checked with the live page, local CSS/JS and all 33 covers at 320, 768, 1024 and 1440: no horizontal scroll, the buttons scroll the shelf and disable at the ends. Don't re-run `palefire_special_publications_move.php`: it would cut the shelf back to 5. Why: the flat row hid most of the guide, and the covers read as pictures rather than books.

- **Object view: an oral history clip no longer shows two players.** Opening the page at a wall entry's address (e.g. `…/mossville#mossville-s-early-families`) opens the object view in the next animation frame, which can come before Drupal attaches behaviors. The behaviors' `once('pf-audio')` then found the view's copy of the clip, already set up by `panelAudio()`, and built its controls again: two play buttons, two sliders, and a panel that scrolled sideways. `initAudio()` now skips a player that is already set up (`is-custom`, which `panelAudio()` clears before rebuilding). Checked on the live page with the address: one player on load, also when the race is forced (the copy's once mark cleared and behaviors re-attached; the live JS showed two), and play and the timed transcript still work after stepping to the next clip. Why: reported from the live Mossville page.
- **Reading rail: the current section's name runs vertically down the rail.** The name that shows beside the rail as a section's heading rises was a horizontal tag up to 22rem wide, so it ran into the content (over "05 From the Galleries" on Special Collections). It is now set vertically, reading up with its letters facing the page (turned 180°), from its segment's top, 20px wide, so it keeps to the narrowest gutter (3.75rem) with 8px to spare (`_toc-rail.scss`). Its length is capped at the rail left below its segment (`--pf-rail-room`, set by `modular-toc.js` on load and resize) and 20rem, ending in "…" when cut. The check that hides it over text now samples down its length. Ticks mode starts the name level with its tick. Checked on the live page with local CSS at 1024 (the narrowest rail width): the label sits at x 32–52 beside content at 60. Why: the tag covered the section's own eyebrow, and the gutter is too narrow for a horizontal name on most sections.
- **Oral histories: the transcript fills with the accent as it's heard, like a lyrics view.** The phrase being heard is now in the accent (Twilight's pale gold, at least 6:1 in every reading theme) with no wash behind it. Every phrase before it stays in the accent, and what's still to come stays muted, so the reader sees how far the clip has come. Seeking back unlights the later phrases. Hovering a phrase that's still to come shows it in the heading color, since a click plays from there. Checked on the live page with local JS/CSS at 1440: current and past phrases #f2dc9b, upcoming #b7b3ba, no background. Why: the user asked for a progress view like Spotify's lyrics.
- **Building map: the tooltip pointer no longer covers the photo.** The pointer is a rotated dark square half inside the label; below a room that inner half painted over the top of the photo (at times, depending on paint order around the photo's color animation). It's now clipped to its outer half plus a sliver that covers the gold rule at its base. Checked on the live page at 1440x1000 with the Gallery's label below the room.
- **Ask form: a filled field's rule doubles to 2px.** A filled field's underline was only a color step from muted to the heading color, which is close on Twilight. It now also thickens to 2px, so a finished field stands out by weight as well as color, autofilled fields included (`_ask.scss`). Empty fields keep the full-strength muted rule (3:1, WCAG 1.4.11). Focus (3px, accent) and errors (2px, red) still win and stay distinct from it. Why: filled and empty fields looked almost the same, and dimming the empty rule would have dropped it below 3:1.
- **Oral histories: the transcript lights up phrase by phrase as the clip plays.** A clip with a WebVTT file beside its audio, under the same name (`01-helen-bradley.m4a` → `01-helen-bradley.vtt`), gets a `data-pf-timings` URL (`palefire_preprocess_paragraph__exhibit_audio()`). Once the clip has played, the phrase being heard shows in the heading color on a soft wash, and the rest of the transcript dims to the muted color (still 4.5:1). The highlight crossfades from phrase to phrase (instant with reduced motion). The text scrolls along only while the reader can still see the previous phrase, so it won't pull back someone who scrolled away. Clicking a phrase plays from that phrase (mouse only; keyboard users have the seek slider). The cues load only once the transcript is on screen, and a clip without a `.vtt` works as before. `scripts/palefire_oral_history_vtt.py` (dev-only, Python + faster-whisper) makes the files. Whisper only provides the timings: its words are lined up with the published transcript, one cue per sentence (long ones split at a comma), the cue text is the transcript's own wording, and speaker labels are left out. Mossville's 69 clips are in `scripts/data/mossville-vtt/` and get copied next to the audio on the server. Checked on the live page with local JS/CSS/VTT: every cue was found in its transcript, and the lit phrase matches the audio. Why: to make following an oral history easier to read along with, as discussed for the exhibitions with audio.
- **Written at Hill: quieter, and after Time in the Archive.** At 1440px the section was two rows of 230px covers, taller than the exhibitions above it, for what is supporting content. The Covers layout is now one row: a swipeable strip until the section is 60rem wide, then five books at most 11rem each. Only the short title shows; the subtitle is kept for screen readers. `palefire_special_publications_move.php` moves the section after "Time in the Archive" (the programs, then the books that came from them) and keeps the five newest books (`keep=<n>` to change). It runs as a dry run unless `apply=1`.
- **Special Collections hero: the kicker on one line, and one gold button.** "LSU Libraries — Hill Memorial Library" broke onto two lines from 1024px up, because the kicker sits inside the `<h1>`, which is held narrow so "Special Collections" breaks in two. From a 30rem-wide cover it now takes its own width on one line (`_landing-swiss.scss`). Phones still wrap it under the name. The researcher card's Log in is no longer filled gold: it is outlined like Register, with the full-strength control edge (3:1) and its arrow in the accent, so Search is the hero's only filled button. Hover turns both buttons gold as before. Checked with the live page and local CSS at 320, 768, 1024, 1440 and 2000: the kicker is one line from 768 and Log in has no fill, with no horizontal scroll. Why: the wrapped kicker read as two labels, and three gold blocks competed in one screen.
- **Special Collections hero: no gap between the hours and the intro.** The researcher card shared the intro's grid row with its foot level, so its extra height pushed the intro down, leaving about 150px under the hours at 1440. The card now spans the hours' and intro's rows, and the title's from 48rem (where the title keeps to the left half; under that, the one-line title would run into it). The title keeps the whole row, so its kicker stays on one line. Checked on the live page with local CSS at 320, 768, 1024 and 1440: 16-24px between the hours and the intro (36px at 768, where the card is a little taller than the two rows), no overlap, no horizontal scroll.
- **Who to ask: rows are buttons, like the ledger's.** Each contact row is now one large target: the email link covers the row (its `::after`), with a line-drawn envelope where the ledger's arrow sits and the address beside it. The topic is set at the ledger's title size in the heading color. Hover or keyboard focus anywhere on the row turns the topic and envelope to the accent (gold on Twilight), the envelope lifts 3px (not with reduced motion), and focus outlines the whole row. The person's name is still its own link to their profile, above the row. Its screen-reader name is "Email <name> about <topic>: <address>". The rows use fixed column shares, so every name lines up. Checked with the live page at 320, 768, 1024 and 1440 in Twilight: clicking the topic opens the email and the name opens the profile, with no horizontal scroll. Why: the rows read as plain text with small links, and the rest of the page's lists are full-row targets.
- **Special Collections hero: a researcher card replaces the three boxed links.** The glass toolbar (University Archives, Oral History, Log in) read as generic buttons and gave the log in no context. Now one framed card beside the intro, like a museum's membership panel: "Researcher account" in the gold label voice, "Request items" (in the Reading Room or as digital copies), Log in filled like the Search button and Register outlined in the hairline, both to Aeon in a new tab, then "First time? Read the guide" to How to Use Special Collections (or the Quick Guide's own link). Aeon makes the account on a first log in, so Register goes to the same URL (`register_url` in `landing.html.twig`) until it has a page of its own. University Archives and Oral History leave the hero. The card is two banners wide from 80rem (its end on the Search button's), the intro's other half from 40rem, and under the intro on phones, where the buttons stack full width. Its plate is 80% (was 65%), so the gold label keeps 4.5:1 over a white video frame (about 5.8:1). The entrance staggers the card's rows. Checked on the live page with local CSS and markup at 320, 768, 1024 and 1440: no horizontal scroll, the card's foot level with the intro's. Why: the log in and the guide needed a clear, purposeful place.
- **Staff: a directory, profiles and "Who to ask", under /v2.** The live /staff page loads all 94 profiles at once (1.5 MB of HTML), each with its whole CV in a modal, and its `#name` links aren't pages. New: `/v2/staff` is one ruled list (photo or initials, name, job, department, email, phone; about 60 KB for 94 people, no CVs) with search and a Department filter that run in the browser and stay in the address (`?department=special-collections&q=…`), so links can open it filtered. `/v2/staff/<slug>` is a real page for each person: portrait, contact, subjects (each searches the directory) and the CV fields as folded sections, the first open. The slug is the person's existing alias (`/lilibedoya` → `/v2/staff/lilibedoya`), and an old-style `/v2/staff#lilibedoya` goes on to it. Both are pages of one View, `palefire_staff`, which only provides the routes. The theme reads the Staff nodes (`src/StaffDirectory.php`, `palefire_preprocess_views_view()`), and an unknown slug is a 404. The pages sit under /v2 so they get this theme and leave the live /staff and /<name> pages alone. "Who to ask" (`staff_contacts`) is a section of topics, each with a person, linked to their profile, with the department's staff at the rule's right end. `palefire_staff_setup.php` creates the View, the two paragraph types and the `pf_staff_thumb` / `pf_staff_portrait` image styles. `palefire_staff_contacts_section.php` adds the section to /v2/special, matching people by job title. Also fixed: pages other than Modular scrolled 12px sideways from 1024px up (the row's gutter was wider than the container's padding). Checked with the live page's chrome and real staff data, with local CSS and JS, at 320, 768, 1024 and 1440 in light and Twilight: no horizontal scroll; the filter, search, count, address and old-link redirect work. The PHP was not run: there is no PHP on this machine. Why: Special Collections needed a way to reach its ~28 staff, and /staff needed replacing.
- **Written at Hill: real book covers.** `palefire_special_publication_covers.php` swaps the typeset jackets for the books' own covers, as Image media named "Cover: <title>" in `public://publications`. The research guide's covers are used where they are full size (Amazon originals, 1707x2560); the guide's UNC Press link for The Carceral City is dead and four others are under 480px, so those come from LSU Press and Bookshop.org (Ingram) instead, all at least 660px wide. The script downloads on the server by default. With `images=<folder>` it reads a copy of the local, gitignored `_reference/publication-covers/` instead. It runs as a dry run unless `apply=1`, and leaves existing covers alone unless `replace=1`.
- **Tile showcase: Covers layout, and "Written at Hill" on Special Collections.** Books that used Hill's collections, brought over from the research guide's autoplaying carousel, which fails WCAG 2.2.2 and hotlinks its covers from Amazon. `palefire_publications_setup.php` adds a Publication paragraph (title, author, publisher and year, catalog link, optional Cover media), the Publications field and a "covers" Layout on Tile showcase, and a `publication_cover` image style (480 wide, no crop). It also makes Tiles optional on Tile showcase, since a required Tiles field kept the Covers and Ask sections from being saved in the UI. A book with no cover gets a jacket set in type, on purple, ink, gold or paper in turn. The covers stand on a hairline, rise 6px on hover (none with reduced motion) and fade in with tile-reveal.js. Narrow screens get a swipeable row, then 3 columns from 32rem and 4 from 48rem. `palefire_special_publications.php` adds the section after "Off the Walls, Online" with the guide's eight newest books, linked to their catalog records. The guide stays the full list, linked as "All publications". It also corrects the guide's "Samuel G. Freeman" to Freedman.
- **Building map: every tooltip photo is a 4:1 band.** For consistency, the narrow photo from tight spots is now the only one: `pf_map_room` crops the middle of the photo to 960x240 (WebP) and the tooltip shows it 4:1 (108px at full width), so every label with a photo is the same shape, about 361px tall. The setup script replaces the style's old 960x640 crop on its next run (which flushes the old derivatives) and the Photo help text now says 4:1. `place()` no longer shortens photos (`.is-tight`, `tipPhotoMin` removed); a label that fits neither side still slides into view, keeping its pointer when the slide is under 10px. Run against the live layout at 1440px wide: at 1000px tall the Gallery's label sits below the room with its pointer; at 640px it slides under the breadcrumb bar.
- **Building map: a tight tooltip shortens its photo instead of dropping it.** Where the full label (photo on top, about 468px) fits neither above nor below its room, `place()` now gives the photo whatever height the text leaves on the roomier side (`--tip-photo-h`, a tighter centered crop in the same layout), down to `SETTINGS.tipPhotoMin` (96px, a 4.5:1 band); only below that does the label slide into view without its pointer. Run against the live layout at 1440px wide: at 1000px tall the Gallery's photo shortens to 99px and the label fits below the room; at 640px it slides under the breadcrumb bar with a 96px photo.
- **Building map: the Lobby is numbered on phones.** Phones hide the drawing's names and number the rooms instead, so the Lobby, a meeting point but not a room, couldn't be found there. `buildings.yml` gains `landmarks` (the Lobby, Floor 1), which the list shows first under Walk in on phones only, as plain text, and `building-map.js` numbers with the rooms, its marker on the drawing's Lobby label (now `data-map-label="lobby"`). No hover area or tooltip: wider screens are unchanged. Not checked on the live page (the numbering runs in the aggregated JS).
- **Building map: tooltips placed within the visible part of the map.** `place()` treated the whole stage as free space, but the sticky drawing slides under the header and the landing's breadcrumb bar, so a label "above" its room could sit half under the bar (the photo hidden behind it). It now measures what the window shows of the stage, below the header and bar, and tries above, then below; if a label fits neither, it drops the photo (`.is-tight`), then takes the side with more room; on a window too short for even that, it slides into view without its pointer (`.is-adrift`). It re-places on page scroll (once a frame), since scrolling changes what the bar covers. The header and bar stay above the map. Checked by running the new `place()` against the live page's layout at 1440x1000 and 1440x640: the label stays clear of the bar, with the photo dropped where it can't fit.
- **Building map: room Photo is a Media reference.** Like every image in Modular, a room's Photo is now an Image media item picked in the Media Library (`field_space_media`), so a photo is uploaded once with its alt text and credit and can be reused, in place of the first version's plain image field. `palefire_building_map_setup.php` adds the reference (the core Image type, as `palefire_exhibit_setup.php` picks it) and deletes `field_space_image` while no room uses it; if one does, it lists those rooms and leaves the field. The preprocess reads the photo through `_palefire_media_image_item()`; the tooltip's markup, styles and the `pf_map_room` style are unchanged.
- **Building map: room photos in the tooltips.** A room's Photo (`field_space_image`) now runs across the top of its wall label, under the gold rule: the `pf_map_room` style (960x640 WebP) shown 2:1 with a centered crop, so the taller label still fits beside its room. It starts in black and white like the drawing and turns to color over 1.6s once the label opens (an animation, so it replays when moving room to room); with reduced motion it stays black and white. Alt is empty, since the label names and describes the room. The hidden tooltip sources load the photos lazily (so never); `building-map.js` preloads them with the serif when the pointer or focus first reaches the map. A label that fits neither above nor below its room now goes on the side with more room. Phones (map under 30rem) show no photo. Checked on the live page at 320, 768 and 1440px with a stand-in image; the preload and placement changes in `building-map.js` weren't exercised there (the live JS is aggregated).
- **Building map: Tooltip note and Photo fields on rooms.** `palefire_building_map_setup.php` now adds two optional fields to Building spaces terms: Tooltip note (`field_space_note`, up to 40 characters), printed after the access line ("Public walk-in · Now showing"), and Photo (`field_space_image`, at least 960x640, stored under `building-spaces/`), plus the `pf_map_room` image style (960x640 crop, WebP) for the tooltip's photo strip. The note shows as soon as it's filled in; the photo isn't drawn yet (the theme side comes next). Re-running the script is safe: it only creates what's missing.
- **Building map: roomier vertical spacing in the tooltips.** Option A from the padding mockups: the body's top and bottom padding go from 1.5/1.75rem to 2/2.25rem (the floor column's to 2.125rem, so "Floor" stays level with the access line), with 1.125rem under the access line, .875rem under the room name, and 1.5rem + 1.125rem above the link. Phones get 4px more at the top and bottom. About 22px taller. Checked on the live page at 320 and 1440px.
- **Building map: more room inside the wall-label tooltips.** The label felt tight at its edges. Body padding goes from 1.125/1.375rem to 1.5/1.75rem, the floor column from 4rem to 4.75rem, with more space under the access line, the room name and above the link; the cap goes from 25rem to 27rem so the text keeps its measure. The narrow (phone) layout gets a little more padding too. Checked on the live page at 320 and 1440px.
- **Building map: an open tooltip no longer lifts the drawing over the breadcrumb bar.** The stage rose to z-index 3 to clear the hero's summary, which tied with the sticky breadcrumb bar; coming later in the page, the drawing painted over the bar while a tooltip was open. It now rises to 2: enough to beat the summary, heading and event card (all 2, all earlier in the page), and under the bar. Checked on the live page at 1024 and 1440px with the McIlhenny Room tooltip open.
- **Building map: tooltips set as museum wall labels.** At 19rem and 14px, the tooltips read as browser tooltips beside the hero's type and the room names. Now up to 25rem wide: the floor numeral in its own column, the room in the exhibition serif (Playfair Display 500, lining figures) under a gold rule, the text at the 16px body size, and a flat solid panel (no blur or shadow, so the unused `--pf-map-tip-bg` glass token is gone). On a map narrower than 30rem the floor column folds into a small-capitals line above the access line. `building-map.js` loads the serif when the pointer or focus first reaches the map, so the first tooltip doesn't swap fonts. The floor column reads "Floor 1" to screen readers, so the access line no longer repeats it. Checked on the live page at 320, 768, 1024 and 1440px.
- **Building map: tooltip hover delay shortened from 0.5s to 0.2s.** Once a tooltip is open, moving room to room skips the wait, so only the first hover waited and the long first wait made the rest feel broken. At 0.2s the first and later hovers feel alike, and a sweep across the map still doesn't flash tooltips. `--pf-map-tip-delay` in `_building-map.scss`.
- **Building map: Floor 1 Gallery label centered on its room.** The label sat low and right on the Gallery's hatched area (80 map units right of and 22 below its center), while Floor 2's sits on its center. `hill-memorial-map.svg.twig` moves its anchor from 1993, 2376 to 1913, 2354, so the word's center falls on the region's center (2091, 2247). Measured on the live page with the new coordinates applied; it clears the elevator icon.

### 2026-10-30

- **Ledger: no outer rules on large screens.** From lg, a ledger with no Categories (Teaching & Research) sat just under the section's rule and just over the next section's rule, so its own top rule and last row's bottom rule doubled them. `_tile-showcase.scss` drops both from lg, so the hairlines run only between rows. Ledgers with Category labels (Partnerships) keep theirs, and below lg the list keeps both, since there it stacks under the lede. Checked in headless Edge at 800 and 1200.
- **Footer: Today's status reads as the row's value.** "Closes soon" and "Closes at 5 PM" were both grey at the same size, and the second line sat 3px left of the first: its 1rem indent didn't match the diamond's box and gap. The Today row's hours are now a two-column grid (`_footer.scss`): the diamond hangs in the gap before the value column, so the status starts on the same line as Where, Call and Ask. The status is in their color, and today's hours are a smaller grey line under it, on the same edge. Checked in headless Edge at 360 and 700.
- **Teaching & Research: "Ask about a program" goes to the question form.** The section's link opened AskUs in a new tab (`https://askus.lib.lsu.edu#special`), though Ask Special Collections' form is on the same page. Its Link is now `/v2/special#ask-question`, so `footer.js` glides to the form, focuses it and plays the gold frame, as the footer's Ask and the Quick Start's "Reach out" do. Being internal, it no longer opens a new tab. Content change, made in the UI; `scripts/palefire_special_programs_section.php` uses the same URI for new installs. No theme code changed.
- **Preview draws Modular pages as published.** The edit form's Preview (`/node/preview/{uuid}/full`) passes the node as `node_preview`, not `node`, so Palefire didn't see a Modular page there. The page used the generic `container-xxl` / `row g-4` / `col-12` layout (gutters around the landing that shifted the Quick Start and its band, and a separate "Home" breadcrumb strip), `<html>` got no `data-pf-mode` or `data-pf-hero`, and the TOC and the Special Collections footer were missing. New `PalefireHooks::pageNode()` returns the node on the canonical, latest-revision and Preview routes (Preview in Full view mode only). `preprocessHtml()`, `preprocessPage()` (which now also sets `node` for `page.html.twig`) and `currentModularNode()` use it. New `pageAlias()` picks the footer from the alias in the edit form, so a Preview of a `/v2/special` page gets the Special Collections footer. A new node with no alias yet (Pathauto makes it on save) still gets the Main Library footer. The breadcrumb trail is still built from the `/node/preview` path, so a Preview shows only Home. Pairs with the theme rule for `node/preview*` set in the UI. Why: editors saw a different layout in Preview than on the live page.
- **Header and hero come in as one sequence.** Only the banners had an entrance; the rest of the first screen just appeared. New `scss/components/_entrance.scss` choreographs the first load: the logo, the menu's sections and the utility icons drop in from the bar (about 0–0.5s), the breadcrumb row and video button slide in, the title rises, the unit name's hairline draws out and "Hill Memorial Library" follows, then today's hours, the intro, the links plate and its cells one by one, the search field, and the banners (their existing rise, retimed to start at 1.14s after the search). The cover video settles from a 6% push-in over 2.4s. It is all CSS, keyed to `html.pf-ready` so it starts with the page fade instead of behind it. If the fade never readies, nothing waits. It uses opacity and the individual `translate`/`scale` properties, filled backwards only, so hover transforms, the links plate's scroll unblur (kept in its animation list) and the glass blur are untouched. The title starts at opacity .01 so it still counts for LCP. The header plays once per full load because it is outside `#swup`. The hero replays on Swup visits. It is off with reduced motion and with the Settings drawer's motion switch. Checked on the live Special Collections page with the CSS injected, at 320 and 1440, with no horizontal scroll.
- **Header logo lines up with the breadcrumb.** The wordmark's drawing begins 24 units into its 433.91-wide viewBox, so its "L" sat 6.6–8.7px inside the gutter the breadcrumb starts on. `.pf-brand__logo` now has `translate: calc(-24 / 433.91 * 100%) 0`. Because it's a percentage of the logo's own width, it follows the compact bar's resize, and the layout doesn't move. Checked on the live Special Collections page with the CSS injected: the "L" and "Home" both start at 16, 24, 24 and 48px at 320, 768, 1024 and 1440, with no horizontal scroll.
- **"colour" → "color" throughout.** US spelling everywhere: comments, README (the "Reading colors and Settings" heading and its anchor), visitor-facing strings (the Settings help text in `utility-panels.html.twig`, alt text and setup copy in `scripts/`), and the `pf-banner-color` keyframes (renamed in `_landing-features.scss` and `css/style.css` together). Quoted historical text keeps its spelling: Audubon's *Ornithological Biography* passages in `audubon.json` and the 1857 Calliste title in `aves.json`. Content already imported into Drupal is unchanged until the scripts are re-run or the nodes are edited.
- **Ask Special Collections: no "Full answer on AskUs".** The AskUs page shows the same answer word for word, so the link only sent readers away to read it again, and the button beside the byline looked heavy. It is gone from the homepage FAQs and the guide's FAQ list (`guide-faqs.html.twig`), with `.pf-inquiry__meta`; "Answered by" is the answer's last line again. `_palefire_ask_faqs()` no longer reads the tile's Link. "All Special Collections FAQs" still goes to AskUs. Checked on the live page at 320 and 1440.
- **Ask Special Collections: answer links fixed, AskUs as a button, room around answers, a sequenced reveal; Visit: arrow on All library hours.** The answers still showed full `https://` URLs: most are bare URLs in the stored text, linked by the text format's URL filter after `_palefire_ask_answer_tidy()` ran. The link tidy (a link repeated in brackets dropped, URL text without `https://`) moved to a `#post_render` callback, new `src/AskAnswer.php`, which runs after the filters. Under each answer, "Answered by" and "Full answer on AskUs" share a row (`.pf-inquiry__meta`), the link now the framed mono button the section's other links use (`.pf-inquiry__button`); an open answer has a little more space under its question and about twice the question's padding before the next rule, so the next question reads as a new start. On first scroll into view, each column's parts (the FAQ heading, each question, the two buttons; the form's heading, intro, each field, Send question) rise 16px and fade in 60ms apart, both columns side by side, as the Collections rows do (new `js/ask-reveal.js`, library `ask-reveal`, attached only by `ask-special.html.twig`); off for reduced motion and without JS, and focus anywhere in the section shows it all at once. The Visit strip's "All library hours" button had no icon; it gets the up-right arrow Google Maps has (it leaves for LibCal). Checked on the live page with the HTML, CSS and JS injected, at 320 and 1440 (no horizontal scroll); the styles went out with 859215b.
- **How to Use Special Collections: every FAQ.** A new "Frequently asked questions" section lists every Special Collections FAQ tile, most asked first. This is the order of the homepage's Ask Special Collections section (search weight, then title), without its limit of eight. A guide Text paragraph that is only `[special-collections-faqs]` draws them after its text as the guides' accordion, each with its answer, byline and AskUs link (`palefire_preprocess_paragraph__guide_text()`, `includes/guide-faqs.html.twig`; `_palefire_ask_faqs()` now takes no paragraph and no limit). The list follows the tiles, so a new or reweighted FAQ shows up without editing the guide. `scripts/palefire_guide_faqs_section.php` adds the section before "Get help". It changes content only: no fields, settings or CSS. The theme code went in with b15a9ad. Checked by injecting the markup into the live guide at 320 and 1440 (no horizontal scroll); not yet rendered by Drupal. Why: the guide answered how to use Special Collections but not the questions people ask most, and the homepage shows only eight of them.
- **Exhibitions: a script for "Mirror lead image".** `palefire_exhibit_lead_mirror.php` creates the boolean field and puts its checkbox under "Show whole image" on the gallery form, in the same field group if there is one, moving the fields below down one. Why: so the field doesn't have to be made by hand in Manage fields. Only adds; safe to re-run.
- **Exhibitions: "Mirror lead image".** A gallery's lead can face away from the text over it (Audubon's opening bird looks off the page). A new checkbox on the gallery (`field_exhibit_lead_mirror`, boolean) flips the lead left to right on the page in all three openings (cover, full, compact): `.pf-exhibit-mirror`, a `transform: scaleX(-1)` on the image only, so the washes on its frame stay where they are and the cover's rise (which animates `scale`) still runs. Focal points and pans follow the flip. The lightbox builds its own image, so opened full screen it shows the original. Without the field the theme does nothing. Add it by hand: Structure → Paragraph types → "Exhibition: Gallery" → Manage fields, Boolean, machine name `lead_mirror`, label "Mirror lead image" (also in `palefire_exhibit_setup.php` for new sites).
- **Ask Special Collections: readable FAQ answers.** The answers came from AskUs as one run-on block: paragraphs flattened to double spaces, links shown as full URLs (some twice), "Answered by" run into the text, and one answer pasted in twice. New `_palefire_ask_answer_tidy()` (palefire.theme) splits them back into paragraphs, drops a repeated block and a link repeated in brackets, shortens URL link text (no "https://"), and moves "Answered by" to a quiet mono byline under the answer. Answers with their own paragraphs keep them; stored content is unchanged. The answer now has a 38rem measure and 1.7 leading (`_ask.scss`). Checked on the live page with the HTML and CSS injected, at 320, 768, 1024 and 1440: no horizontal scroll.
- **Quick Start Guide: "Reach out" goes to the question form.** Option C's "Reach out" jumped to the top of the Ask Special Collections section, so on phones the form was still a scroll away. It now goes to the form itself (`#ask-question`, passed as `ask_href` in `landing.html.twig`) and frames it in gold, as the footer's Ask link does: `footer.js` now handles any link to `#ask-question` on the page that has the form (glide, focus, frame replayed on every click). Guides without the form still send Reach out to AskUs.
- **Special Collections footer: Ask's gold frame on every click.** The frame played only on the first click: the panel stayed `:target`, so CSS had nothing new to start it, and Chrome sometimes skipped a repeat jump to the `#hash` already in the address. On the home page, `footer.js` now glides to the form itself (`Drupal.palefireGlide`, as the "On this page" links do), replaces the address, focuses the panel, and restarts the frame on arrival. From other pages the link works as before. Settings' "Reduce motion" now holds the frame, as the OS setting does (it had cut the animation to nothing).
- **Special Collections footer: Ask goes to the form.** The footer's "Ask Special Collections" row left for AskUs although the Special Collections home page has its own question form just above the footer. It now links to that form (`/v2/special#ask-question`, the panel in `ask-special.html.twig`), which, on arrival, is framed in a gold hairline with a faint gold wash outside its box (outline and box-shadow, so nothing moves), fading in and out once over about three seconds. `:target` covers loads and in-page jumps, `:focus` covers Swup visits (which focus the anchor without updating `:target`). Keyboard focus keeps the gold ring; reduced motion holds the frame without the fade. CSS only, in `_ask.scss`. The Main Library footer is unchanged.
- **Special Collections hero: room under the banners.** The Quick Start Guide's shaded band began 28px under the banners' labels (at 1440), while it had 68px above rule 01 and under its last line. It now starts that same 68px down, so the three spaces match (`--pf-quick-band-top`: the guide's padding in `_landing-features.scss`, the band's clip in `_sections.scss`, which keeps its old start elsewhere). Checked on the live page with the compiled CSS injected, at 320, 768, 1024 and 1440.
- **Edit form tidy: Modular pages only, second pass of wording.** The first dry run would also have collapsed the old theme's Section, Service and Tile forms, renamed guide and exhibition fields that already read well, and called From the stacks' "Featured item" "Cards". `palefire_edit_form_tidy.php` now touches only the Modular page and the paragraph types it can hold, rewrites labels only on the landing sections, and limits "Cards" to Tile showcase. New wording for Height, Snap into place when scrolling, background video, still and image, Hide lead panel (now the same on every hero), Quick guide fields, Banners, Sections, Cover image and Authorized editors.
- **Special Collections hero: Visit links as buttons, and room under the Quick Start Guide.** "All library hours", "Open in Google Maps" and "Parking" were underlined text links among the hero's framed buttons. On the landing cover they now use the Quick Start Guide's button rule (mono capitals, hairline frame, gold on hover, arrow at the end, 17.5rem wide); Google Maps and Parking sit side by side across the map's column and wrap when it is narrow. Other pages' Visit strips are unchanged. The Quick Start Guide's shaded band ended flush on its last line (no bottom padding on the hero); it now has the same space under its content as the band shows above its rule, and the next index rule's gap counts from the band's foot (`_landing-features.scss`). Checked on the live page with the compiled CSS injected, at 320, 768, 1024 and 1440: no horizontal scroll.
- **Edit forms: shorter, collapsed sections.** The Special Collections edit form ran to many screens: every section and tile opened in full. New `scripts/palefire_edit_form_tidy.php` sets every Paragraphs field to open collapsed (one summary row each, so dragging reorders them; opening one closes the others; Edit all / Collapse all, Duplicate and Add above) and closes details field groups. It hides the Auto / Light / Dark section theme from the forms (saved values stay, so pages look the same), renames the Layout option "masonry" to "Default (Grid / List switch)" and lists it first, and rewrites the labels and help text of the section fields this repo defines. Form display and field settings only; no templates or CSS. Dry run by default.
- **Special Collections hero: search paused.** The hero's search sent words to the libraries' Bento search, since there is no Special Collections search yet. Its field and button are now `disabled` and the placeholder reads "Search coming soon" (short enough for 320px). The label stays "Search Special Collections" for screen readers. Markup only (`search_off` in `landing.html.twig`), with no CSS changes, so the hero looks the same to editors reviewing it. Other landings are unchanged.
- **Request paths: option C reads "Ask an expert for help".** It read "Ask an expert help", missing "for" (`guide-request-paths.html.twig`, so the Quick Start Guide and the full guide both change).
- **Ask Special Collections: a button to the guide.** Under the FAQs, "How to use Special Collections" (`/v2/special/guides/special-collections`) now sits beside "All Special Collections FAQs", and both are buttons in the Send question style (mono capitals, hairline frame, gold on hover) instead of an underlined link. Side by side when the column holds both, stacked otherwise (`ask-special.html.twig`, `.pf-inquiry__links` and `.pf-inquiry__button` in `_ask.scss`). Why: the FAQs answer one question each; a first-time researcher needs the whole process, and the guide had no way in from this section.
- **Request paths: the Find Item panel links to the catalog.** The panel described searching the catalog but gave no way there. It now has a full-width "Search the library catalog" button under its text (`https://catalog.lib.lsu.edu`, new tab, so the guide stays open), in the Quick Start Guide's button style. Option C's "Find Item" stop was plain text; it is now the same stop as in A and B, so it opens the same panel too (`guide-request-paths.html.twig`, `.pf-paths__cta` in `_guide.scss`).
- **Page transitions: Back works after leaving for an old-theme page.** A menu link to a page still on the old theme (Origami) broke Back: Swup pushes the history entry as soon as the visit starts, then `palefire-swup.js` found the page incompatible and loaded it with `location.assign()`, which pushed a second entry for the same URL. Back from the old page landed on that extra entry and loaded the old page again. The fallback now uses `location.replace()`, so the visit leaves one entry and one Back returns to Palefire.
- **Special Collections: shorter rows in the ledger.** "Time in the Archive" repeated "Special Collections" in three of its four row titles, on the Special Collections homepage itself, and the repeat wrapped each title to two lines. Ledger rows now drop a leading "Special Collections" ("Research Grants", "Faculty Fellowship", "Summer Project"). The node titles keep it, because they head their own pages and will show in search results. Other layouts are unchanged (`palefire_preprocess_paragraph__tile_showcase()`).
- **Ask Special Collections: one Send button.** The live form showed two: Drupal draws a submit button through its wrapper (`#theme_wrappers: input__submit`), not `#theme`. `palefire_form_libanswers_ask_form_alter()` set the `<button>` template as `#theme`, so the usual `<input>` was drawn around it. It now replaces the wrapper (`#theme_wrappers: ['input__submit__libanswers']`). Why: a duplicate button, one without its arrow.
- **Ask Special Collections: questions go through Drupal and the LibAnswers API.** The form posted straight to the AskUs widget endpoint (`askus.lib.lsu.edu/1.0/form/submit`). LibAnswers now refuses that with 449 unless the post carries its ALTCHA proof of work, so every question failed. The form now comes from a new module, **`lsulibraries/libanswers`** (its own repository, installed with Composer). The module creates the ticket in the Special Collections queue (2616) through the documented API (`POST /api/1.1/ticket/create`), so staff answer it in LibAnswers as before. Honeypot's hidden field guards it. Its time limit is left off because it would stop `/v2/special` from being cached. The API credentials live in `settings.php` only.
  - **Theme:** `palefire_preprocess_paragraph__tile_showcase()` renders the module's form (`AskForm`, queue 2616, return fragment `ask-special-collections`). `palefire_form_libanswers_ask_form_alter()` strips the controls' wrappers and adds the section's classes and blank placeholders. `templates/form/libanswers-ask-form.html.twig` keeps the earlier markup (labels, required marks, error lines, status line). `input--submit--libanswers.html.twig` makes Send a `<button>` with the arrow. Without the module, the panel links to AskUs.
  - **Script** (`js/ask-form.js`): the same checks and messages, then a background post to Drupal (`X-LibAnswers-Fetch: 1`), which answers JSON: 200 when sent, 422 with field errors (shown under the fields), 502 when LibAnswers refuses it. Without JavaScript the form posts as usual and returns to the section with a message.
  - **Styles:** controls reset `margin` (Drupal's `form-text` is also a Bootstrap class), and the old honeypot rule is gone.

  Checked in a local render of the Drupal markup with the real script, on a dark section, with Drupal's answers stubbed: errors on an empty send; the post to the page URL with the header, build ID and Honeypot field; server field errors under the email; a Honeypot or LibAnswers failure pointing to AskUs. No PHP runs here, so the module itself is untested until libstage. Why: the old endpoint now requires an undocumented bot check, while the API is documented and keeps spam protection in Drupal.
- **Special Collections: no unit line, the breadcrumb under the logo, and the bar's glass fades in step with the header.** "A unit of LSU Libraries, Louisiana State University" under the search is removed (`landing.html.twig`, `_landing-swiss.scss`): the label over the title and the footer already say it, and under the field it read as help text. The hero's breadcrumb now starts where the LSU Libraries logo does, at the header's side padding (`--pf-header-gutter`), at every width; it was under the menu's first item from lg. The cover's breadcrumb bar had a glass fade that never ran: the reading-rail rule's `transition: padding-inline-start` replaced it, so the bar's glass snapped on and off. That rule no longer sets a transition, and the fade is staggered with the header's (`$pf-chrome-stagger`, 0.12s, both 0.3s): scrolling down, the header turns to glass, then the bar; back at the top, the bar clears first, then the header. Reduced motion keeps both instant. Checked on the live page (local CSS, old rule stripped, markup mirrored): breadcrumb and logo both at 16, 24, 24 and 48px at 320, 768, 1024 and 1440; at 1440 the running transitions are the header at 0ms and the bar at 120ms scrolling down, and the reverse at the top. Why: the line didn't belong, the breadcrumb should share the logo's edge, and the two bars should move together.
- **Special Collections: "Reach out" jumps to the Ask section.** In the request paths (`templates/includes/guide-request-paths.html.twig`), option C's first stop is now a link, in the same style as the stops that open panels: the whole stop is the target, underlined on hover, with the icon brightening. The Quick Start Guide on `/v2/special` passes `ask_href: '#ask-special-collections'` (`landing.html.twig`), so it scrolls to Ask Special Collections below the header without a reload. Guides, which have no Ask section, link to AskUs: Special Collections. Checked on the live page with the new markup swapped in: a click on the stop's note lands the section 49px down, just under the header. Why: "AskUs or email form" now has its form on the same page.
- **Ask Special Collections: filled fields visible on every swatch.** The filled-field rule used the section's text color, which on Twilight (and other reading swatches) is the same grey as the muted rule (`#b7b3ba`), so filled and empty looked alike on `/v2/special`. Filled rules, hover and the typed text now use the headings' color, `--pf-inquiry-strong` (`--bs-heading-color`, near-white on Twilight, as the FAQ questions are). Checked on the live page with the new CSS: filled `#f8f7f9`, empty `#b7b3ba`.
- **Ask Special Collections: filled fields show they're done.** A filled field's rule steps up from the muted grey to the text color, so visitors can see at a glance what they've filled in. Focus keeps the only accent rule (3px, gold on most swatches), so a filled field never looks focused or "accepted", and errors stay red. Text fields know they're empty by a blank placeholder (`placeholder=" "`, read as nothing), and the menus by their "Select one" prompt. The rule is in `:where()`, so focus and errors always win. Checked in a local render on a dark section: filled white, empty grey, focus gold on a filled menu, a filled but invalid email red. Why: a long form is easier to finish when its progress shows.
- **Ask Special Collections: styled menus and autofill.** The question form's menus are customizable selects (`appearance: base-select`) where the browser supports them (Chrome and Edge 135+). They stay native `<select>`s, so keyboard, screen reader and phone behaviour don't change.
  - **The list:** a panel in the section's color under the field, as wide as it, with a hairline frame and a soft shadow. It fades and drops in, with no motion under reduced motion.
  - **Options:** 44px rows (WCAG 2.5.8) with the fields' padding and a pointer cursor. Hover and keyboard focus share a wash of the text color, and focus adds an accent bar at the start. The chosen option has a check in the accent and medium weight, so selection is never shown by color alone.
  - **Prompt and arrow:** "Select one" is muted, in the field and the list, until something is chosen. The chevron turns over while the list is open.
  - **Other browsers:** Firefox and Safari keep their own list, in the section's colors.
  - **Autofill:** autofilled fields no longer show the browser's pale box. The section's color is painted over it, and the focus rule still shows.

  Checked in a local render in Edge 156: opened with a real click and the arrow keys on light and dark sections, 44px rows with 8px/16px padding. Why: the open lists looked like the operating system's, not the theme's.
- **Ask Special Collections: the Send button in the Quick Start Guide's style.** "Send question" is now the same outlined button as Full guide, Prices, Parking and Hours (`.pf-quick-guide__link`): mono capitals (`%pf-landing-label`), a hairline frame that turns gold on hover and focus, the words at the start and the arrow at the end, 17.5rem wide (the full width on a phone). It replaces the solid light block. Checked in a local render on light and dark sections at 320 and 1440. Why: one button style across the page.
- **Ask Special Collections: menus that read on dark sections.** The question form's open menu showed white text on a white list on dark sections. The browser draws the list on the select's background, which was transparent, while the options took the section's light text. The options now take the section's own solid colors (`--pf-section-bg`, else the swatch's page color). The menu arrow is now a thin 1.5px chevron in the text color, not a filled triangle. Focus on every field is no longer a box: the rule under it turns the accent and thickens to 3px, over a 5% wash of the text color. Checked in a local render on light and dark sections. Why: the open menu was unreadable on `/v2/special`, and the focus box looked heavy beside the ruled fields.
- **Ask Special Collections: FAQs found by a looser Subcategory match.** The section and `palefire_ask_section.php` found no FAQ tiles on production, though 17 have the Subcategory "Special Collections". Drupal matches a text field's `=` with `LIKE`, so a stored value with stray spaces (as some imported Subcategories have) never matched. Both now match Subcategories containing "Special Collections". When the dry run still finds none, it lists the FAQ tiles' Subcategories exactly as stored (quoted, unpublished counted apart), so the right value is easy to see. Why: the section showed only the form.
- **Special Collections: Teaching with Special Collections joins the program pages.** `scripts/palefire_special_programs.php` now also builds "Teaching with Special Collections" as an unpublished exhibition (`…/teaching`), reworded from the research guide of that name (guides.lib.lsu.edu/c.php?g=1544354).
  - **Title page:** the label "Class visits", the key facts "100+ classes a year", "2,500+ students a year" and "Instructors in any field", and a "Request a class" button to the LibWizard class request form.
  - **Galleries:** what a visit offers, the two sample activities (Object Identification, Reading Maps) as entries, 16 past classes with the materials pulled for them in an accordion, and how to request a class. The request gallery names John Miles and links to the Faculty Fellowship as the next step.
  - **Item names:** as the guide gives them, with four spellings corrected (Bartram, Archimedes, Labyrinte de Versailles, Ovid's *Metamorphoses*).

  `palefire_special_programs_section.php` lists it first, then the Fellowship, Research Grants and the Summer Project. The section's eyebrow is now "Teaching & Research", and its lede reads "Class visits, fellowships and grants for instructors, scholars and students whose work starts in Hill's collections." Why: class visits are the easiest way in for any instructor, and the Fellowship is the year-long step after them.

- **Special Collections: ask a question without leaving the page.** A new Tile showcase layout, **Ask** (`ask`), shows FAQs beside a question form. `scripts/palefire_ask_section.php` adds it to `/v2/special` as "Ask Special Collections".
  - **Form** (`templates/includes/ask-special.html.twig`, `js/ask-form.js`): the same fields, options and hidden values as the Special Collections LibAnswers widget (19447), posted in the background to `askus.lib.lsu.edu/1.0/form/submit`. Questions land in the same queue (2616) as ones sent from AskUs, so staff work as before. It checks required fields and the email before sending, with an error under each field and focus on the first. The result is shown in a status line, with a link to AskUs if sending fails (including a CAPTCHA request, status 449). It keeps LibAnswers' honeypot field. The library is attached only to this section and has no new dependencies.
  - **FAQs** (`_palefire_ask_faqs()` in `palefire.theme`): Tiles picked in the section, else up to eight published Tiles with Category "FAQ" and Subcategory "Special Collections", by search weight then title. They show as the guides' accordion, each linking to its full answer on AskUs, then "All Special Collections FAQs" (LibAnswers group 4711). The list refreshes when a Tile changes (`node_list:tile`).
  - **Layout and styles** (`scss/components/_ask.scss`, classes `pf-inquiry*`, since `pf-ask` is the Ask Us drawer's): one column on phones, FAQs and form side by side from lg. Fields are ruled, with labels in the mono eyebrow style. Field rules use the muted text color, not the 20% hairline, so a control's edge meets 3:1.

  Checked in a local render with the compiled CSS and this JS, on light and dark sections, at 320, 768, 1024 and 1440: no horizontal scroll, the form's fields two across once it has 30rem. Also checked: errors and focus on an empty send, a bad email, and the sent fields and messages with LibAnswers stubbed for success, 449 and the honeypot. No test question was sent to the live queue. Why: the AskUs page is a lot to take in for one question; this keeps visitors on the Special Collections page and still reaches the same staff.

- **Exhibitions: title pages for programs, and the program pages as exhibitions.** Three optional fields on the exhibition title page, added by re-running `palefire_exhibit_setup.php`:
  - **Label** (`field_exhibit_kicker`): replaces the word "Exhibition" over the title, e.g. "Faculty fellowship".
  - **Key facts** (`field_exhibit_keyfacts`): any number of "Label: value" lines. They show as a `<dl>` row over the prompt, each fact after a hairline, label over value, wrapping to one per row on a phone. A line splits at a colon followed by a space within its first 40 characters, so times and URLs stay whole.
  - **Main button** (`field_exhibit_action`): a link shown as the primary button. It needs no script. "Begin" and "Contents" join its row in the outlined style.

  Exhibitions without these fields look as before. `scripts/palefire_special_programs.php` now builds Research Grants, the Faculty Fellowship and the Summer Project as exhibitions, not guides:
  - **Title page:** a label, a sans title, a subtitle, an intro and three key facts. The Apply button goes to the page's "How to apply" gallery until an editor points it at the form. The Summer Project's goes to the Office of Undergraduate Research.
  - **Galleries:** about the program, its people (the four 2025–26 grant recipients and the five 2024 fellows, one entry each, reworded from LSU's announcements, with their librarian collaborators), how to apply, and questions.
  - **Still to do:** current terms remain `[Editor: …]` notes, and the pages have no title images until editors add them.

  Checked on the live Audubon exhibition with the new title page parts added (local CSS) at 320, 768, 1024 and 1440 in Twilight: facts three across from 768 and one per row at 320, the main button leading the buttons, no horizontal scroll. Why: the programs ask people to take part, so their pages should draw them in like an exhibition rather than read like a reference guide.

- **Special Collections: a script for the Fellowships & Grants section.** New `scripts/palefire_special_programs_section.php` puts a Tile showcase in the Ledger layout after "Off the Walls, Online" on `/v2/special`. It has the Eyebrow "Fellowships & Grants", the Title "Time in the Archive", a Lede, the Link "Ask about a program" (Ask Special Collections) and the three program pages from `palefire_special_programs.php` as its rows. Snap, height, theme and TOC visibility are copied from the section it follows. If the `ledger` Layout value is missing, the script adds it. It's a dry run unless `apply=1`. It saves nothing while a program page is unpublished, so visitors never see the heading without its rows. It also stops if the section already exists or the homepage has a newer draft than its published revision. Modular pages have no Category, so the rows show as one list with `<h3>` titles. Why: the section lists the programs from their own pages, not Tiles, and goes live in one step once they're published.

- **Special Collections: program pages for Research Grants, the Faculty Fellowship and the Summer Project.** New `scripts/palefire_special_programs.php` builds three unpublished Modular pages in guide mode, one per program. Each has an intro, sections (About, who can apply, award or pay, how to apply, past recipients or fellows) and a Questions section linking Ask Special Collections. The text is reworded from LSU's own announcements: the 2025–26 Research Grants news, the 2023 and 2024 Faculty Fellowship news, and the Office of Undergraduate Research's Summer Project page. Those report past cycles, so past figures are dated ("in 2023 fellows received a $2,000 stipend"), and current amounts, dates, eligibility and contacts are `[Editor: …]` notes for Special Collections to confirm. Pages have the "Special Collections" subject when the term exists and no guide owner. The script skips any page whose title already exists. Why: the Partnerships section will list these programs as Modular pages rather than Tiles, so each needs a page of its own.

- **Tile showcase: a Ledger layout, for Partnerships on Special Collections.** New `ledger` value for the showcase's Layout field. It lists the tiles with no pictures and no numbers, grouped under their Category labels in the order each label first appears. Tiles with no Category go last under "More", or form one group with no heading when no tile has a Category. Each row is the title, its intro and an arrow between hairlines. From a 40rem container the intro sits beside the title on a shared baseline. From lg the section becomes two columns: the Eyebrow's index rule (with its Link, e.g. "Propose a partnership") across the top, the heading and Lede on the left (5 parts), the ledger on the right (7). Group labels are `<h3>` in the index's accent and rows are `<h4>`. With no groups, rows are `<h3>`. Row titles take the heading color (near-white on Twilight, where the intros are the soft grey). Rows rise 12px on scroll as the index does, and the arrow steps right on hover and focus. Both are off with reduced motion. No new JS or library. `palefire.theme` builds `groups`, and `_tile-showcase.scss` holds the styles. To use it, add `ledger|Ledger` to the Layout field's allowed values by hand, as `index` was. Checked on the live page (local CSS, the section's markup mirrored) at 320, 768, 1024 and 1440 in Twilight: no horizontal scroll, a long single-word title wraps, two columns from 1024. Why: a calm, typographic home for partnerships that reads differently from the two picture sections above it.

- **TOC: no second list on guides or in the phone drawer.** `js/modular-toc.js` builds the rail's hover index panel (`.pf-toc__panel`) whenever the page has the rail layout, but its styles apply only in rail mode (lg and up, not guides). On guides at every width, and in the TOC drawer below lg, it showed after "Back to top" as a second, unstyled "Table of contents" list. `_toc-rail.scss` now hides the panel by default. Rail mode still shows it (`display: flex` with a more specific selector). Checked on the live Donating Materials guide and the Audubon exhibition (local CSS) at 390 and 1440: hidden on the guide at both widths and on the exhibition at 390, shown on the exhibition's rail at 1440 with all 8 rows. Why: the panel belongs to the rail, and elsewhere it repeated the table of contents.

- **Hero banners: color comes in on scroll.** The banners under the Special Collections cover were grey and half washed with the page color until hovered, because in full color they were too loud on load. They now stay grey on load and take on their own colors as they scroll up: from the moment half a banner is on screen to when it reaches the middle (`animation-range: entry 50% cover 50%` on the banner's existing pan timeline), and not before the page has scrolled 15rem (`scroll(root)`). So the row is grey on load at every width, including 44–64rem, where it shows without the rise. Scrolling back up greys them again. Hover and focus still bring the color back at once (0.9s in, 0.5s out). Three registered numbers drive it (`--pf-banner-grey`, `--pf-banner-wait`, `--pf-banner-dim`), read by both the grey filter and the wash's opacity. Only hover's value has a transition: a `filter` transition restarted on every scroll frame and left the picture trailing the scroll, and a property under an animation won't transition. CSS only, no JS. With reduced motion, the Settings drawer's switch, or no scroll-driven animations (Firefox), the banners stay grey until hovered, as before. Checked on the live page (local CSS) at 320, 768, 1024 and 1440: grey on load at every width, full color by mid-screen, hover easing both ways, no horizontal scroll. Why: a quiet first view that still rewards scrolling.

- **Exhibitions: the short-opening wash lifts and settles instead of turning, and arrives already set.** Two problems with the turned wash. First, opening `#elephant-folio` showed the long wash, then turned it over 1.8s while the section faded in. The opening is off screen when measured, so the browser restyled it only as it came near, and the transition ran in view. Second, "Read more" seemed to bring the long wash in at once. Blending the angle and stops passed through a state darker than either end, and most of that darkening came in the first 0.3s. Now the side wash never changes: a mask (`pf-lift-mask()`, 10deg, keeps the wash to 44%, clears it by 90%) lifts it off above a short text, and turning slides the mask's fade along, so each pixel only darkens or clears. The look is close to before: lower left solid, the image clear above it. `--pf-wash-turn` transitions only when the reader opens or closes the text (`.is-wash-live`, set by `briefWash(page, true)` from "Read more" and "Show more"). On load or resize it is set at once. Checked on the live Audubon page (local CSS and JS, the stale live transition cancelled) at 1440: `#elephant-folio` gets the short state at 1.4s while still hidden, and shows already lifted. After "Read more" the turn is 0.94 at 0.3s, 0.71 at 0.55s and 0.37 at 0.8s, and frames at fixed turns darken steadily with no dip. Text contrast is 6.9:1 or more folded and open, and Suggested Reading is 7.3:1 at 1440 and 1920. Why: the wash should arrive in its state and settle calmly.

- **Special Collections: the building joins the label over the title.** The label now reads "LSU Libraries — Hill Memorial Library": the Libraries' name in the swatch accent, a short hairline in the accent, then the building (the Visit card's name, `visit.name`) in the muted label tone (`.pf-landing__kicker-place`). The building is no longer repeated in the hours line under the title (`.pf-landing__hours-place` removed from `landing.html.twig`), and the heading reads "LSU Libraries, Hill Memorial Library: Special Collections". On phones the building wraps under the name with its hairline first. Checked on the live page (local CSS, markup mirrored) at 320, 768, 1024 and 1440 in Twilight and Cream: one line from 768, two at 320, no horizontal scroll. Why: the mockup had the building there, and naming it once reads cleaner.

- **Guides: Image groups, and a draft "Donating Materials to Special Collections" guide.** A guide section can now hold an Image group (`exhibit_gallery`), so a guide can show example items, not only text. In a guide section the group is flagged `in_guide` (`palefire_preprocess_paragraph__exhibit_gallery()`) and gets `.pf-exhibit-group--guide`. That class sets the exhibition tokens (`--pf-exhibit-muted`, `-surface`, `-mono`, `-prose`), which only `html[data-pf-mode="exhibition"]` set before. It also attaches the new `palefire/guide-gallery` library. `js/guide-gallery.js` (about 100 lines, on `palefire/viewer`) opens a picture in the full-screen viewer with its caption, and the arrows step through its group. Focus returns to the picture on close. Without the script, the link opens the largest image. Exhibitions are unchanged. `exhibition.js` isn't loaded on guides, so their other links keep opening in the same tab. Allowing the block is a hand change in the UI: tick "Image group" in Guide section's `field_guide_blocks`. `scripts/palefire_guide_donating.php` then builds the guide unpublished at `/v2/special/guides/donating-materials`. It covers what Special Collections collects, what it can and usually can't accept, examples from its collections, making the gift (deed of gift, appraisals, copyright, restrictions) and whom to contact. The four examples reuse the Audubon, Occult and Mossville exhibitions' media. Two more are left for the curators as editor-only "Image to add" figures, and "[Editor: …]" notes mark what only they can confirm. Contacts are from lib.lsu.edu/special. Why: donors need to see what Special Collections takes, and Gina suggested example collections.

- **Special Collections reads as LSU Libraries: the name over the title, a unit line under the search, and a larger wordmark.** An admin said the page didn't look enough like an official LSU Libraries page. The hero's `<h1>` now opens with "LSU Libraries" as a small mono label with a short hairline after it (`.pf-landing__kicker`), so the heading reads "LSU Libraries: Special Collections". Under the search field, "A unit of LSU Libraries, Louisiana State University" sits in the muted label tone (`.pf-landing__unit`). Both are on the landing hero only (`landing.html.twig`, `_landing-swiss.scss`). From xl, the always-compact header's wordmark grows from 120px to 156px (`$pf-logo-compact-width-xl`; 38px tall, so the 49px bar keeps its height), and `--pf-logo-w` follows so the breadcrumb still lines up with the menu. Every color comes from the reading swatch (its accent, the muted label tone): no LSU purple or gold, which clashed with the reading colors. A purple LSU bar and an "LSU Libraries /" breadcrumb were mocked up and left out for now. Checked on the live page (local CSS, markup mirrored) at 320, 768, 1024 and 1440 in Twilight and Cream: no horizontal scroll, the bar stays 49px, the label is 7:1 or more and the unit line is 7.8:1 or more. Why: the page should say whose it is without fighting the reader's color choice.

- **Exhibitions: the reading rail's section name stays off the text, and short openings get a lighter, turned wash.** After a TOC jump to Suggested Reading, the section's name beside the rail (the peek) sat over the paragraph. The section is too short for its heading to rise, so the label never faded. `js/modular-toc.js` now hides the peek in two cases: where the label would cover text (sampled under it with `elementFromPoint()`), and after a jump or a load with a #hash, until the reader scrolls 48px. The label fades (0.25s) instead of blinking, and the peek still shows while a heading rises during normal scrolling. From lg, an opening over a lead whose title, facts and text fill no more than 42% of the image's screen, or a wall intro folded behind "Read more", gets `.is-brief` (`briefWash()` in `js/exhibition.js`). Its wash turns from the side wash (90°) to a 210° one (written as 30deg from its solid end, solid to 32%, clear by 86%), which uncovers the image's upper left. "Read more" or "Show more" turns it back. The angle and stops move together through a registered `--pf-wash-turn` (1.8s, eased at both ends so it settles in after the text opens; none under reduced motion), so the wash sweeps round instead of crossfading, and the scroll-in reveal animations are untouched. Checked on the live Audubon page (local CSS and JS) at 1024, 1440 and 1920: Suggested Reading after a TOC jump shows no label over the text; brief at 1440 and 1920 and full at 1024, where its text is half the screen; Octavo Edition is brief folded and full once opened; text over the washes measures 6.8:1 or more in every state. Why: the label shouldn't cover what was just chosen, and a short opening can show more of the plate.

- **Readings: subtitles on the covers, and the full citation back under them.** With the subtitle only under the cover, a book's cover showed half its title ("The Double Elephant Folio" without "The Story of Audubon's Birds of America"). The cover is now set like a real one: the title, then the subtitle smaller and in italics (Playfair 400, 6cqi, at most three lines) under the same rule. The rule moves up to 38% so four lines of title and three of subtitle clear the foot, still at one height across a row. The byline stops after three lines, so two editors on a phone's cover never reach the rule. The caption is the full citation again, a line each: author (mono), the whole title in the heading color (a book's in italics with its subtitle, an article's in quotes), then the imprint. The cover is decoration again (`aria-hidden`), and a linked reading is named by the caption plus "(opens in a new tab)". Titles on the cover stop after four lines as a safety, since the caption has them whole. List view now reads from the caption: author over a 1.25rem Playfair title, then the imprint, with the cover shrunk to a 4px spine in its color and the arrow at the right. Checked on the live Audubon page with all 25 readings as the new templates print them (local CSS and JS) at 320, 768, 1024 and 1440, in Grid and List: 15 subtitles on covers, no text past a cover's edge, no byline reaching the rule, rules level per row, no horizontal scroll. Why: covers should show the whole title, and the full citation under each reads better.

- **Reading lists: full width and a Grid / List switch; the tile showcase loses its Masonry view.** A gallery body was held to the guide's 52rem, so with the Compact opening the Audubon covers still filled only 832px of 1320. An Image group of readings (`.pf-exhibit-group--readings`, flagged by the new `palefire_preprocess_paragraph__exhibit_gallery()`) now takes the gallery's full width (7 columns at 1440), and the body's other blocks keep 52rem. Such a group gets the tile showcase's Grid / List switch beside its heading (same buttons and styles, in the exhibition's mono voice). List is a ruled index: the cover becomes the row, byline over a 1.25rem title, the cover's color kept as a 4px spine, the caption under it, and the arrow at the right. `js/tile-views.js` now runs both kinds of list. Each kind keeps its own pick in this browser (`pf-tile-view`, `pf-reading-view`), and a pick changes every list of its kind on the page. Only the cards on screen morph. The Masonry view is removed from the tile showcase (template button, column packing, `[data-pf-view="masonry"]` styles); Grid and List stay, a saved "masonry" pick opens on Grid, and the layout keeps its machine name `masonry` so existing sections need no change. The setup script's label for it is now "Cards (tall cards, with a Grid / List switch)" (new sites only). Also fixed: a short book title on a narrow cover ran to the cover's edge (a later `max-width: 100%` beat 80cqi). Checked on the live Audubon page (reading groups rewritten to the new template, local CSS and JS) at 320, 768, 1024 and 1440: full width from 1024, 2/3/5/7 columns, the switch beside the heading, List in both groups after one click and remembered, no text past a cover's edge, no horizontal scroll. Beyond the Basics on the Special Collections home at 390 and 1440 with local CSS: Grid (the lead card included) and List unchanged, no overlap. Why: the Compact gallery should use its width, the list should be browsable either way, and Masonry is no longer wanted.

- **Readings: titles line up, the subtitle is quieter, and the arrow shows at rest.** On the live Audubon list the covers looked mismatched. Book titles sat at the foot, so a two-line title started lower than a three-line one, and titles over 45 characters dropped a size. The rule and title now start at 52% of every book cover, so they line up across a row (four lines still clear the foot). Every title is one size, apart from titles over 90 characters, which get one smaller step as a safety; none on the Audubon list is that long. Under the cover, a book's subtitle and imprint are one line in the caption color, the subtitle italic, instead of a subtitle in the heading color that read as a second title. The line underlines on hover only. Linked figures (readings and linked images) show their arrow chip at rest, not only on hover, so a reading without a link is told apart at a glance, with no dimming; hover still nudges the arrow. A linked reading's byline or masthead stops short of the chip and breaks long names. Checked on the live Audubon page (all 25 readings, HTML rewritten to the new template, local CSS) at 320, 768, 1024 and 1440: titles at one height per row, one title size, nothing overflowing a cover, bylines clear of the arrow, no horizontal scroll. The live CSS still has the old caption rules, which the test can't remove; production drops them on deploy. Why: the covers should read as one set, with the title said once.

- **Audubon: Suggested Reading as covers, by script.** `scripts/palefire_exhibit_audubon_readings.php` replaces the gallery's 25 typed citations with readings, so nobody has to enter them by hand. It adds two Image groups after the text, Selected works (23: 18 books, 5 articles) and Fiction (2), in the page's order. 21 readings link to their catalog record or article; four have no link (the 1854 magazine piece, Lewis, Partridge, Prince), as on the page now. It then cuts the text from "Selected works:" on, keeping the catalog search and guide paragraphs. Places of publication are left out (the fields have none); the two Patterson editions put the editor after the author. The Lindsay record's stray tab is gone (the record id is used). `dry-run=1` lists the changes and saves nothing. The real run saves a new revision, so a published page changes at once and can be reverted. It stops if the gallery already has readings. Not run; no PHP here to lint it. Why: the list is too long to enter by hand.

- **Exhibitions: readings typeset as covers.** `paragraph--exhibit-reading.html.twig` and `palefire_preprocess_paragraph__exhibit_reading()` render Exhibition: Reading (the setup script's type) in an Image group as a 2:3 cover with no image. The cover and the line under it make one citation, with nothing repeated. A book is a cloth color picked from its title: eight tones (forest, straw, ink blue, oxblood, sage, umber, plum, vermilion), each 5.2:1 or more, the same in light and dark. It has a spine shadow, the author in mono caps at the head, and the title in Playfair at the foot under a short rule. Its subtitle (after the first colon) and the imprint go under the cover, the subtitle italic in the heading color. An article is an offprint on paper: the journal as a masthead over a full rule, the title in italic quotes, the author at the foot; its issue goes under the cover. The cover is real text in reading order (author, title, journal; the masthead is moved up with `order`), with hidden full stops and a colon, so it reads as a citation. A linked reading's name is the cover, then the caption, then "(opens in a new tab)". Type scales with the cover's width (`cqi`), and titles over 45 and 90 characters step down a size, so a whole title always fits with no clamp. Quotes and a final full stop typed into the title, and a final full stop in the publisher or details, are removed. Linked readings share the linked images' behaviour: the whole figure is the link, another site opens in a new tab, the arrow chip and zoom show on hover, and the first line under the cover underlines. A reading without a link has none of these. Linked figures no longer underline their contents (`.pf-exhibit-figure__out`). Checked on the live Audubon page with local CSS and sample readings (6 books, one without a link, and 2 articles) at 320, 768, 1024 and 1440: no horizontal scroll, 2–3 columns, every title fits its cover (Low's *Concordance* included: its subtitle goes under the cover), the masthead is on top, hover and the caption click work. The Twig and PHP were checked only as their output in that test (no PHP here). Why: Suggested Reading shows every book and article as a cover, with nothing to upload and nothing said twice.

- **Exhibitions: setup script for readings.** `scripts/palefire_exhibit_reading_setup.php` adds the paragraph type Exhibition: Reading (`exhibit_reading`) for reading lists: Kind (Book or Article), author, title, publisher or journal, details, and the existing `field_figure_link`. It allows the type in the Image group and renames the group's add button from "Add Image" to "Add Item" if it hasn't been renamed. It is config only and safe to re-run. The fields' help text tells editors to leave out quotes and italics. Not run yet. The cover template and styles follow in their own patch; until then readings render nothing. Why: Suggested Reading should show every book and article as a typeset cover, with no images to find or upload.

### 2026-10-29

- **Exhibitions: book covers that link out (Suggested Reading).** An Image (`exhibit_figure`) with the new optional `field_figure_link` links its image to that URL instead of opening the lightbox. The whole figure is the link, and its caption (the citation) gives the link its name (`aria-labelledby`). Another site opens in a new tab, like the exhibition's other outside links. It says "(opens in a new tab)" and shows the outside-link arrow in the enlarge mark's corner chip. The arrow fades in on hover or focus, always shows on touch screens, and nudges up and right. In an image group these figures are covers: 2:3, `object-fit: contain` on the panel color with a hairline frame, in smaller columns (two at 320px), with `sizes` set so the 640 version loads. The title in the citation (`<em>`) is in the heading color, underlined on hover. `markOutside()` skips these links. With no link nothing changes. Checked on the live Audubon page with local CSS and a sample group in Suggested Reading at 320, 768, 1024 and 1440: no horizontal scroll, 2–3 columns, the arrow chip and zoom on hover, and the caption click goes to the link. The field has to be added in the UI before editors can use it. Why: the reading list should show the books, and the arrow says the visitor is leaving the page.

- **Phones and tablets: gallery openings fade their photograph in and out in place, and the text fades in as it scrolls.** Below lg, Covers and walls with a lead now get the desktop's held photograph: it fades in where it rests while its gallery comes up and fades out in place at the gallery's foot, never sliding. The text is not held there (a phone's toolbar changes the screen as it scrolls, and a held page feels stuck). The wash rises from the foot (30% of its height, eased) and fades in over 20–75% of the gallery's entry. Each text part (title, facts or links, opening text) fades and rises in on its own timeline between 8% and 40% of a screen after it comes up past the foot, so they follow one another wherever the layout puts them, behind the wash. Mossville's walls with no intro: the spacer's wash fades in the same way. To hold exactly on phones, the photograph's entry is now measured in `dvh` (the screen as it is) and its exit hold starts where the frame (sized in `svh`, so a toolbar never re-crops it) unsticks: `exit calc(100dvh - 100svh)`. On desktop both are 0, so nothing changes there. The photograph rules moved out of the lg block, and the area also declares the long-text veil's timeline (`--pf-cover-read`). Checked on the live Audubon Cover and Elephant Folio wall at 390 × 844 and 768 × 1024, and Mossville at 390 (mobile emulation, local CSS): the photograph's top stays at 49px through entry and exit, the wash is 79% or more in when each title starts, no horizontal scroll. The desktop wall was re-run at 1440: unchanged. Emulation has no moving toolbar, so the `dvh`/`svh` hold is not checked on a real phone. Why: phones should get the same calm opening, without a hold.

- **Phones and tablets: no more flickering wash on long-text Covers.** Below lg, a Cover whose text runs past the screen (`.is-long`) had its veil and its text's fold fade driven by `--pf-read`, which `readCover()` wrote on the whole gallery every scroll frame. Each write restyled the gallery and repainted a gradient mask over all of its text, and on phones the wash flickered while it caught up with the scroll. With scroll timelines the veil is now a CSS animation on the area's own timeline (`--pf-cover-read`, inset by the header, 0 to 82% over 40% of a screen, the same curve as before), the text has no fold fade there, and `readCover()` stops writing `--pf-read` wherever CSS covers it (below lg, and from lg with motion, where the reveal already dropped the fold fade). Without scroll timelines, and from lg with reduced motion, it works as before. Checked on the live Audubon in Louisiana Cover at 390 × 844 and 768 × 1024 (mobile emulation) with local CSS: the veil runs on the timeline (0.16, 0.59, 0.82 at steps of a fifth of a screen), no mask. The flicker itself only shows on a real phone; not checked on one. Why: the page should scroll as smoothly as the text it shows.

- **Gallery openings: the side wash slides in from much further, over longer.** It now starts half its width to the left (was a sixth) and slides in over 64% of a screen of scrolling (was 40%), eased gently at both ends (`$pf-reveal-slide`, `$pf-reveal-slide-ease`) so the whole travel reads; the fade takes 36% (was 32%). The wash's right part is clear, so no edge shows however far it travels. To give it room, the hold is 90svh (was 76svh) and the title starts at 36% (was 20%), when the wash is about 95% in place; then the facts or links and the text 12% apart, all in by 84%. Long-text Covers: the wash slides over 44%, fades over 24%, and the text starts 56svh lower (was 46svh), fading in from 18%. Checked on the live Elephant Folio wall and Audubon in Louisiana Cover at 1440 × 900 with local CSS: the wash goes -50%, -30%, -13%, -5%, 0 at 10% steps of a screen, no edge mid-slide, the wall's title holds to the pixel, the long text moves with every step. Why: the slide was too short and quick to read as a move.

- **Gallery openings: a gentler side wash, a light foot, and long text that scrolls while it fades in.** The side wash now fades in ease-in-out over a third of a screen (was linear over 14% of one) while it draws in from the left (a sixth of its width, eased over 40%). The title waits until it is about three-quarters in (from 20%, was 16%). From lg the foot of a gallery opening with text had kept the old solid fade to 22% of the screen, a dark band over the photograph's lower edge; it now has the title page's light one (at most 40% of the page color over 3–6rem), on the frame's `::before` so the sliding wash leaves no bare corner. Openings with no text keep their stronger foot. A Cover whose text runs past the screen (`.is-long`, no "Read more") held all that text still for 76svh while it faded in, so the scroll felt unresponsive. It is no longer held: it starts 46svh lower and rises with the scroll from the first move, fading in on the way (title from 10%, then 6% apart), with the wash ahead of it (in by 22%) and no fold fade at the screen's foot. Checked on the live Audubon Cover (long text) and Elephant Folio wall at 1440 × 900 with local CSS: the long text moves with every step of the scroll, the wash is 98% in when the title is 89%, the wall still holds to the pixel. Why: the wash arrived abruptly, the foot read as a dark band, and held long text felt like a stuck page.

- **Gallery openings: the photograph fades in and out in place, and the text comes in while it holds.** Moves the reveal from the title page (24ba376, undone: the title page is as it was before it) to every gallery that opens on a full-screen photograph, a story's Cover or a wall's lead, from lg with motion and scroll timelines. The photograph no longer scrolls: while its gallery comes up the frame is held where it will rest and fades in as the gallery's edge wipes up over the previous one; at the gallery's foot it is held again and fades out (`pf-exhibit-frame-in`/`-out` on the area's timeline, `--pf-hero`). Once it fills the screen, the text holds for 76svh of scrolling (`--pf-reveal`, a registered length) while the wash comes in (2–16% of a screen), then the title, the facts or links and the opening text rise in, 12% apart. The area and its first row run 76svh longer and the text is set back by what is left of the reveal (`pf-exhibit-pin`), so it holds to the pixel; then it scrolls on over the still photograph. Walls without an intro (Mossville): the spacer carrying the wash is held the same way and the photograph's fade starts after the reveal. Anchors and TOC links land at the reveal's end (the section's negative scroll-margin), as do "Begin" and "Next" (`glideTo()`); a Cover's long-text fade allows for the hold (`measureCover()`, `readCover()`). Focus in the text shows it at once. Unchanged: "Full screen, then pan" openings (Occult), below lg, reduced motion, browsers without scroll timelines. Checked on the live Audubon (Cover and wall) and Mossville galleries at 1440 × 900 and Audubon at 1024 × 768 with local CSS: the photograph's top stays at the header's foot through entry, hold and exit, the title holds to the pixel, no horizontal scroll. The script's part was not run live. Why: each gallery should open on its picture, and the picture should never slide.

- **Title page: scroll into the photograph, then the text.** From lg, with motion and scroll-driven animations, the title page opens on the cover photograph alone, its left edge softened into the page, with a small "Scroll" cue. The head runs 90svh longer: over that scroll the photograph and the text hold still while the wash comes in (2–16% of a screen), then the kicker, title, intro, credit, facts and prompt rise in one after another (from 16%, 10% apart), scrubbed by the scroll on the head's own timeline (`--pf-cover`). Past it, as before, the text and wash scroll away over the still photograph. Text too tall to pin on the screen (`.is-tall`, `sizeReveal()` in `js/exhibition.js`), below lg and with reduced motion: unchanged, the text comes in on load. Focus in the text shows it all at once, and "Home" glides to the reveal's end. Checked on the live Audubon page (1440 × 900) and Occult (1024 × 768) with local CSS: the text holds to the pixel through the reveal, the photograph never moves, no horizontal scroll. Why: the title page should open on the picture and let the visitor bring the title in.

- **TOC rail: an index panel replaces the frost and wide rows.** Hovering, focusing or tapping the rail now opens a compact glass panel beside it (`.pf-toc__panel`, built by `buildPanel()` in `js/modular-toc.js`). It lists every section on an even 36px row; the row under the pointer fills and gets a marker, and the current section is in the heading color. A segment and its row light together (`.is-hot`), whichever the pointer is on, and a long panel scrolls to keep that row in view. The panel is a visual copy: `aria-hidden`, with rows out of the tab order, so the rail's links stay the nav for screen readers and the keyboard, and focusing a rail link lights its row. A plain click on a row clicks its rail link, so the glide is the same; a modified click opens the row's own link. Following a link or pressing Esc closes the panel until the pointer leaves or moves to another section. The page-wide frost and the invisible 26rem rows are gone, and the rail's own names now only show as the scroll-linked current name. Checked on the live Special Collections home and Aves with local CSS and JS at 320, 1024 and 1440: the panel opens from a segment with the matching row lit, a sweep down all rows never closed it, a row click glides to its section, and Tab, Esc and touch taps work. Below lg nothing changes. Why: the hover gave no sign of what was clickable, and names spaced by section length were hard to scan.

- **TOC rail: easier to use on hover; read sections step back.** While the names are open, the left of the page is frosted with the sidebar's glass, fading out to the right, and the names drop their own boxes, so they no longer sit on the text. Each row is now as tall as its segment and covers the 4px gap below it, about 26rem wide, so moving from one name to the next never falls between them. The names stay 0.4s after the pointer leaves (`.is-hover`, `RAIL_HOLD` in `js/modular-toc.js`), so slipping off a row doesn't close them. It's a timer, because Reduce motion zeroes CSS transition delays. Esc still closes at once. Segments already read fill in the TOC text color softened toward the track (about 3.3:1 light, 3.9:1 dark), so only the current one is bright. The names keep their color for contrast. Checked on the live Aves exhibition and Special Collections home at 1024 and 1440: a pointer sweep down all the names never closed them, with Reduce motion on and off. Tab, Esc and touch taps unchanged. Why: on the Aves page the names were hard to read over the text and closed while moving between them.

- **TOC: a reading rail replaces the hide/show button (lg and up, not on guides).** The TOC is now a 3px line at the page edge, one segment per section, each as long as its section. The current segment fills as the activation line reads into it (`_toc-rail.scss`, rail code in `js/modular-toc.js`). Hover, focus or a first tap (iPad landscape, touch laptops) shows every name on the sidebar's glass over the content, with no reflow; Esc hides them (1.4.13). The current name shows by itself only while its heading rises from the activation line to 45% of the screen, fading as it goes, so it doesn't sit over what is being read. Segments that can't be 24px tall become even ticks. Guides keep the sidebar, and so does any page whose sidebar region holds other blocks. Below lg nothing changes. Removed: `js/modular-toc-collapse.js` and its library, its three localStorage keys, `html.pf-toc-collapsed` / `pf-toc-instant`, the button's Twig and styles, and the column's slide. TOC labels are now spans (`.pf-toc__label`, `.pf-toc__top-label`). Checked on the live Special Collections home, Oral History, Mossville and the Special Collections guide with local CSS, JS and Twig at 320, 768, 1024 and 1440: no horizontal scroll, the offcanvas button below lg, the guide's sidebar unchanged, and hover, Tab, Esc and tap working. The 1px hero shift at load is in the live page too (it scores higher now because the hero is full width). Why: the toggle cost the content 300px or hid the TOC, and the rail keeps it on screen without either. 693c172 already shipped this change's CSS (its compile picked up the SCSS in progress), so production has had no toggle styles since then; this patch brings the Twig and JS in line.

- **Wall galleries: Read more opens downward.** On a wall with a lead image (Audubon's Octavo Edition), the grid's first row took whatever the image's screen left over the text, so an opened intro shrank it: the title and the paragraph being read slid up to 400px (or the browser's scroll anchoring scrolled the page instead), and at 1024 Show less left the button off screen. While open, that row now keeps its folded height (`--pf-wall-top`, set by `holdWallTop()` in `js/exhibition.js`, measured again on resize), so the text opens down over the image and the image grows with it. Show less keeps the button where it is on screen (the page scrolls up as the text folds), and the browser's scroll anchoring is off during the change. Reduced motion: the same, instantly. Checked on the live Audubon page with local CSS and JS at 320, 375, 768, 1024 and 1440: the title doesn't move on Read more, the button stays within 4px on Show less, no horizontal scroll. Why: the upward jump looked like the page scrolling by itself.

- **Title page: no gap under the photograph.** The cover image stopped .25rem short of the head's foot (`.pf-exhibit-cover__media` clip-path), hidden under the solid foot fade. With today's lighter fade the strip showed as a 4px line of page color between the title page and the first gallery (Mossville, Audubon). From lg the image now runs to the foot. Below lg, where the foot is still solid, the .25rem stays (it keeps a broken line of image from showing at 125% zoom). Checked on the live Mossville page with local CSS at 1440, at 100% and 125%: no row of page color at the join. Why: the gap came from the lighter fade.

- **Title page: a much lighter fade at the photograph's foot.** From lg the cover image used to fade to solid page color over its last 4–8rem, which read as a dark band right above the first gallery's photograph. It now fades to at most 40% page color over 3–6rem (`clamp(3rem, 10vh, 6rem)`), so the edge still settles into the page without the band. `pf-eased-wash()` gains an optional `$strength` (0 to 1, default 1, so every other wash is unchanged). The left wash behind the title is unchanged. Why: the band looked heavy where the title page meets the first gallery.

- **Reverted: gallery cover openings with the photograph beside the text.** Back to the photograph full width under the text with its left wash, as before 6373781 (`css/style.css` and `_exhibition.scss` match 0edfa7f again). The new title page prompt from that commit stays. Why: the split layout looked much worse on the live page.

- **Gallery cover openings: the photograph beside the text, from lg.** A gallery's cover opening (Audubon in Louisiana) no longer washes its photograph out from the left behind the text: the text keeps its column on the page color, and the photograph starts a gutter after it (`left` on `.pf-exhibit-wall-hero__link`) and runs to the screen's edge, cut square, with no wash. Below lg, where the text spans the column, nothing changes. Title pages and wall openings keep their own treatment. Measured on the live Audubon page with local CSS: the text ends at 826 and the photograph starts at 883 at 1440 (634 and 675 at 1024); no horizontal scroll at 768, 1024 or 1440. Why: the left wash read as a gradient box, and where the photograph rose under the title page its top met the page as a hard line over that gradient. (A scroll-driven wash over the photograph's top was tried first and dropped: fading it reopened the line part-way.)
- **Title page prompt: "Scroll through every gallery in order, or choose one from the table of contents."** The default under the credit (`PalefireHooks::exhibitHead()`) used to send readers only to the table of contents; exhibitions now read straight through by scrolling. An exhibition whose Exhibition prompt field is filled keeps its own text, and the field's help text on existing sites keeps the old default until edited in Manage fields (the setup script only creates fields). Why: the old line implied the galleries could only be opened from the table of contents.

- **Exhibition title style: Serif or Sans, and the serif's lines no longer collide.** New field `field_exhibit_title_style` on the title page (re-run `palefire_exhibit_setup.php`): Serif, Playfair Display, the default and also what an empty field means; or Sans, the site's display sans as before Playfair. The `<h1>` gets `pf-exhibit-cover__title--serif` or `--sans`, and only a serif title preloads the font, so a sans exhibition never downloads it. "Easy-to-read font" still sets either in Atkinson. The serif's leading now comes from the font: Playfair's J falls 0.19em below the baseline and its b rises 0.79em, so lines closer than 0.98em touch. At 0.92 the J of "James" ran into the b of "Audubon"; at 1.04 there is a 0.06em gap (measured: 6–7px at 1440, 1024 and 768, 2px at 320). Tracking is eased to −0.015em, with the font's kerning and common ligatures. Sans keeps 0.9 and −0.04em. Checked on the live Audubon page with local CSS, both styles at 320, 768, 1024 and 1440: no horizontal scroll, and Playfair is never fetched for sans. Why: curators get a choice of tone from tested presets, and "John James" was touching "Audubon".

- **Exhibition titles in a serif.** The cover `<h1>` (`.pf-exhibit-cover__title`) is now set in Playfair Display 500, an art-magazine serif (mockup A on the hero canvas), at -0.02em tracking and 0.92 line height, balanced across lines; the size rule by title length is unchanged. The font is self-hosted (`fonts/playfair-display/`, from `@fontsource/playfair-display` 5.3.0, Latin subset, 23 KB, licence in `OFL.txt`), declared in `base/_fonts.scss` with `font-display: swap`, and preloaded from `PalefireHooks::exhibitHead()`, so other pages never download it and no Google request is added. The subtitle stays in the body sans; Settings > Easy-to-read font puts the title back in Atkinson at the sans weight. New `$pf-font-display-serif` in `_variables.scss`. No config: `git pull` and `drush cr`. Why: the sans title didn't suit 19th-century natural-history plates.

- **Transcriptions: horizontal rules only.** The side hairline is gone: it started just below the hairline above the transcription, so the two read as a corner that missed, and the page rules started at the text, short of it. Now everything is flush left: one full-width hairline above "Transcription" and one above each page, with the monospace labels on them. Checked on the live page with local CSS at 1440 and 320 (the aggregate's old side rule cancelled in the harness, as the deploy removes it). Why: it looked broken.

- **Transcriptions set apart from the curators' text.** A text block that opens with a Heading 4 "Transcription" gets `.pf-transcription` (`palefire_preprocess_paragraph__guide_text()`, no config): a hairline above it and one down its side, as a quotation; "Transcription" and "Page 1" as monospace labels with a hairline over each page; Audubon's words in the reader serif (system fonts: Sitka, Charter, Georgia; "Easy-to-read font" wins); the note on conventions in the curators' sans, smaller and muted. It replaces the guides' term-and-definition rule, which had indented only the first paragraph under each heading. Struck words are now announced "crossed out" to screen readers (visually hidden text around `<s>`; yesterday's entry promised it but the rule was missing). More room after it in the object view (30svh, 75svh from lg) so the Hurricane's short last page can still come to the top of the panel. Checked on the live page with local CSS at 1440 and 320: labels 12px mono, note 14px sans, body serif, all #b7b3ba on #272429 in Twilight (8:1); last page heading at the panel's top; no horizontal scroll. Why: the transcription read as more curators' text.

- **Audubon manuscripts: whole pages and transcriptions.** The Manuscripts gallery showed 600 × 400 close-ups of the Children's Warbler and the Bear and the Hurricane's first page. Each now shows every page, made from the Special Collections TIFFs (`Audubon 4K/manuscripts`; the Hurricane's spread cut at the fold), with a transcription after its text. On the wall an image group of three or more figures is a manuscript: its tile is tagged "4 pages" and shows page 2 on hover. The object view turns its pages with Previous page / Next page under the image ("Page 2 of 4", announced; the crossfade is off with reduced motion, and a turn leaves any zoom), and the transcription follows: the shown page's heading is marked and brought under the panel's top row (a glide within a screen, a jump further). New `palefire_exhibit_audubon_manuscripts.php` updates the built page; it stops if the WYSIWYG format strips `<s>`. Checked on the live page with local CSS and JS at 1440, 1024, 768 and 320: pages and headings in step, no horizontal scroll, no script errors. Why: the close-ups showed a few lines of each manuscript, and visitors could not read Audubon's hand.

- **Wall openings: the photograph stays longer; the page slides up over it.** The opening photograph (Mossville's ten galleries) now holds at full strength for the first quarter of a screen of scrolling and is gone by 70% (was: 20% within 8%, gone by 32%). To keep the title readable while it rises over a full-strength photograph, the foot's wash moves from the pinned frame onto the content: the `::before` spacer carries it up with the title, edge to edge, and the wall below sits on the page color (padding in place of its top margin), so the page reads as sliding up over the pinned photo. The gallery number and "N clips" now take the heading color (`--pf-g-heading`, Twilight #f8f7f9), as text over a hero's video does: `--bs-body-color` on these pages is the grey #b7b3ba, so yesterday's switch to it changed nothing and the number stayed at 3.1:1 at rest. Measured on the live page with local CSS from full-screen shots (text hidden, crops of the real pixels, label colors read from the page), all ten galleries at 1440 from 0 to 60% of a screen in Twilight and 0 to 40% in Cream, and three at 375: worst number 5.7:1, title 5.9:1, clips 14.2:1; page width 375 at 375. Why: the portrait was gone almost as soon as the reader started to scroll.

### 2026-10-28

- **Wall openings without an intro: the photograph pins and fades.** From the first scroll the opening photograph (Mossville's ten galleries) stays pinned under the header and fades to the page color while the wall of clips rises over it: most of the way within 8% of a screen (while the title is still on the foot's wash), gone by 32%, then hidden so it can't be clicked through the wall (`$pf-wall-photo` in `_exhibition.scss`: the hero spans the gallery's rows 1-5 on its own view timeline, inset by the header, as the pinned story openings; a `::before` keeps rows 1-4 one screen tall so the title still sits at the photo's foot). Reduced motion or no scroll timelines: it scrolls away as before. Also fixes failing contrast found while checking: the gallery number ("07 / 11", muted tone) fell to 2.1:1 over bright photos (Family & Community, Health & Environment) under the soft foot wash. The number and "N clips" now take the text's full color (in error: see 2026-10-29), and the wash is a little deeper (solid to 10%, gone by 46%, was 6% / 42%). (Contrast figures first given here, 5.4:1 and up, were computed with the near-white text color; on these pages `--bs-body-color` is Twilight's grey #b7b3ba, so the change to it did nothing and the number was still 3.1:1 at rest. Corrected 2026-10-29, above.) Earlier entries' 11.3:1 figures were wrong (clipped shots without the photo) and are corrected below. Why: the portrait hands over to the clips instead of scrolling off as a block, and small labels must hold 4.5:1 over any photo.

### 2026-10-26

- **Wall openings without an intro: a soft rise from the foot, not a corner.** The corner wash (below) was too dramatic: a large solid quarter-ellipse with a visible curved edge. Replaced by the same eased wash the theme uses elsewhere, vertical only, solid to 6% of the image's height and gone by 42% (`pf-eased-wash(to top, 6%, 42%)`); `pf-eased-corner()` is removed. Checked on the live Mossville page with local CSS, all ten galleries at 1440 and 1024: (corrected 2026-10-28: the contrast figures first given here, 7.4:1 and 11.3:1, came from clipped screenshots that left the pinned photo out; full-screen measurements are in the entry above). Why: the photograph should lead; the title only needs a quiet floor under it.
- **Wall openings without an intro: a corner wash.** From lg, a wall gallery whose opening has only its number, title and facts (no intro over the image, as all ten Mossville galleries) no longer gets the wide wash from the left that an intro needs: the page color keeps to the lower-left corner (an eased radial wash, solid to half of an ellipse 80% × 85% of the image, gone at its edge; `pf-eased-corner()` in `_exhibition.scss`), so the portrait shows across the rest. Walls with an intro (Audubon's seven) and every wall below lg keep their wash. Checked on the live Mossville and Audubon pages with local CSS: Mossville's 10 walls get the corner wash at 1440 and 1024 and the full one at 768, Audubon's 7 keep theirs; (the 11.3:1 first given here for the gold title was measured from clipped screenshots without the photo; corrected 2026-10-28). Why: with nothing to read across the left, the wash hid half of each portrait for no purpose.
- **Light reading colors: the header is the page's own glass.** On Cream, Soft cream, Lavender, Sepia and Sage, the header and its dropdowns were inverted to a dark band in the swatch's heading color (near-black on Cream, dark brown on Sepia), which matched neither the page nor its highlighted title. They now take the page-color glass the dark swatches already use: text and icons in the heading color, hovers in the accent, and the soft hairline (the panel muted tone at `--pf-hairline-strength`) under the bar (`_reading-themes.scss`). The header stays clear at the top of video and image heroes, as before. Checked on /v2/special at 1440 and 375 with local CSS in all four light swatches, with the bar stuck. Why: reading modes (Kindle, Apple Books, Kobo) keep their toolbar the page color, and the dark band broke the calm of the light swatches.
- **Clip player: Mute and volume; room after the transcript.** The player (`js/exhibition.js` `initAudio()`) gains Mute (a toggle, `aria-pressed`; the icon's waves become a cross) and a volume slider (`aria-valuetext` "30%" or "Muted"). The level is shared by every player on the page, so the next clip in the object view keeps it. The slider is left out where a page can't set the volume (iOS, which reads it back as 1) and in players narrower than 22rem (container query; phones have volume buttons); Mute always shows. No speed control. In the object view the transcript ends with room (20svh, 40svh from lg) so its last lines scroll up to about the panel's middle. Checked on the live Mossville page with local JS and CSS at 1440 and 375: slider sets 0.3, the next clip keeps 0.3 and the mute, unmute returns to 0.3, one Mute per player, slider hidden on the phone, last line scrolls to 55% / 47% of the panel, no horizontal scroll. Why: listeners need to set the level for where they are, and the end of a transcript was read at the bottom edge.
- **Clip player pinned; transcript unboxed; speaker names wrap.** In the object view a clip's player now pins under the panel's top row (solid page color, hairline under it) while the transcript scrolls, and the clip loses its frame: the transcript is the panel's main text, shown under a "Transcript" label instead of a fold (`js/exhibition.js` `panelAudio()`, `_exhibition.scss`). Fixes horizontal scroll on the live Mossville page on phones: the wall tiles' speaker names didn't wrap (`white-space: nowrap`, made for "Pl. 40"), so "Judy Montgomery-Gauthia and Elaine Robinson" widened the page to 452px at 375; speaker tags now take their own line and wrap (`.pf-exhibit-wall__tag--speaker`, `paragraph--exhibit-page.html.twig`). Checked on the live Mossville page with local JS and CSS (the new class added in the page, as the template isn't live): page width equals the viewport at 320, 375, 768, 1024 and 1440; the player pins level with the top row's foot at 1440 and 375. Why: the player scrolled away with the text, the frame boxed in what is the panel's main content, and the overflow broke phones.
- **Mossville galleries as walls; clips in the object view.** The builder sets the ten clip galleries to Display "wall" (About the Project stays a story), so each gallery opens as a wall of portraits and a clip opens in the object view: photo on the stage; heading, caption, player and transcript (open) in the panel. `js/exhibition.js` sets up the panel's copy of the player again (the copy has no listeners), labels it by the view's title, and stops it when the view steps on or closes. Behind an audio entry's photo, a wash of the same photo (blurred, desaturated, 35% over the reading color, `_exhibition.scss`); never behind objects. Wall tiles of clips show the speaker under the title and the facts say "7 clips" (`_palefire_exhibit_wall()`). Checked on the live Audubon wall with local JS and CSS and a Mossville clip injected, at 1440 and 375, Twilight and Cream: one working player, transcript open, the page's copy paused, the clip stops on step and on close, focus back on the tile, stage buttons keep their 75% backing over the wash. Why: 70 clips scrolled as stories was a long read; a wall is the gallery's index, as at a listening station, and the wash fills the bands beside portrait photos without cropping them.
- **Mossville History Project exhibition.** New `scripts/palefire_exhibit_mossville.php` and `data/mossville.json` build the 2017 oral history listening station as an unpublished exhibition: 11 galleries (the old History, Culture and Economy menus' ten sub-groups, then About the Project), 70 entries of photo, player and transcript, each gallery opening on its first clip's photo. Media is prepared off-site: audio as AAC 64 kbps mono at -18 LUFS (-2.5 dBTP), photos as 2560 px JPEG masters with metadata stripped (391 MB of audio and 169 MB of photos before). Fixes carried over from the old pages: three clips played the wrong file (c34, c48) or none (c52, now unpublished until it has a transcript), interviewers' misspelt names, empty or wrong captions, and alt text that only repeated the caption. `exhibit_audio` inside an entry no longer repeats the entry's title as a second `<h3>`; the entry heading labels it (`paragraph--exhibit-audio.html.twig`, `palefire_preprocess_paragraph__exhibit_audio()`). Why: the old site preloaded 5-17 MB per page, mixed channel levels and loudness, and is a standalone app outside the theme.
- **Special Collections Glossary guide.** New `scripts/palefire_guide_special_collections_glossary.php` builds an unpublished guide page of 37 terms patrons meet when using Special Collections, one guide accordion per stage of a visit: Finding materials (finding aid, Mss. number, record group, container list, provenance…), Requesting (account, Request Item, off-site storage, processed and unprocessed, restricted, embargo), In the Reading Room (non-circulating, lockers, pencil only, book cradle, Camera User Policy) and Copies, rights and citing (duplication order, copyright, public domain, rights statement, permission to publish, preferred citation). Definitions are one or two sentences, with LSU details taken from How to Use Special Collections, which a callout links to (found by title, else its suggested alias). It reuses that guide's Resources. Two LSU-specific points are left as "[Editor: …]" (embargo; credit line or permission form). Why: patrons stumble on archive vocabulary more than on the steps, and a reference page answers a term without retelling the whole how-to.
- **Quick Guide buttons fill the notes' width.** Under Before you visit (`_landing-features.scss`), Full guide, Prices, Parking and Hours sat at their own widths, leaving a gap before the hairline's end. Now they stretch to it: on one line each grows by an equal share; below 555px two equal columns (`minmax(0, 1fr)`, was `auto` from the start), an odd last button across both; under 320px one full-width column. Words stay at the start, the arrow at the end. If Hours fails (LibCal down) the other three share the line. Checked on /v2/special at 320, 768, 1024 and 1440 with local CSS: buttons end on the hairline's edge, no horizontal scroll. Why: the ragged right edge left empty space that read as unfinished next to the ruled notes above.

- **Hill parking guide linked from /v2/special.** Two links to `/v2/special/guides/parking-hill-memorial-library`: "Parking" next to "Open in Google Maps" in the Visit panel (`visit-strip.html.twig`), on the site so in the same tab with a right arrow, its name "Parking at Hill Memorial Library" for screen readers; and a Parking button after Prices under Before you visit in the Quick Guide (`landing.html.twig`), in a new tab like the row's other buttons. The URL comes from `visit.parking_url`, set in `_palefire_landing_visit()` by LibCal location (236, Hill); other buildings get no link until one is added there. The two Visit links share a wrapping line (`.pf-visit__go`, which now takes the `directions` grid area, `_visit.scss`). The Quick Guide's four buttons go two by two below 555px (was 415px for three), an odd last button spanning both columns. Checked on /v2/special at 320, 768, 1024 and 1440 with local CSS and the markup injected: no horizontal scroll. Why: where to park is visitors' most common question before a first visit, and the guide had no way in from the landing.

- **Tile showcase Grid: the first card leads when there are fewer than five.** Five across (a 72rem section), a masonry showcase with one to four cards left columns empty. Now the first card widens to fill the row: 4 cards, two columns; 3, three; 2, three and two; 1, the whole row (`_tile-showcase.scss`, keyed on `data-pf-count` from `paragraph--tile-showcase.html.twig`). Its picture keeps the row's 3:4 column height, so the captions stay level; its title is larger and its page drawing scales up to fill it. Five or more cards keep the even grid, so adding one never reshapes the row; narrower sections, Masonry and List are unchanged. A lead card with a cover gets the landscape crops (`_palefire_lead_cover()`, `tile_showcase_landscape_sm/_md/_lg`) sized for its width, not the portrait crop stretched. The Grid card's single column is now explicit (`minmax(0, 1fr)`): the card's inherited `justify-content: flex-end` had only been hidden by the 3:4 ratio. Editors pick the lead by ordering the Tiles field. Checked on /v2/special at 320, 768, 1024, 1440 and 1600 with 1–4 cards: no horizontal scroll. Why: a gap at the end of the row read as a missing card; one larger card gives the section a clear way in.

- **Page transitions: the new page opens at its top in one jump.** Swup scrolled to the top (or #anchor) with behavior `auto`, which follows the modular pages' `scroll-behavior: smooth`, so a link clicked far down the page showed the new page gliding up as it faded in. The scroll is now instant (`scroll:top`/`scroll:anchor` hooks, `palefire-swup.js`) and happens while the new content is still faded out. Back/forward is unchanged. Why: the long glide read as scrolling the old page, not opening a new one.

- **Page transitions: no more footer flash; softer entrance; prefetch.** Swup faded the whole of `#swup`, and on large screens `#swup` is the opaque curtain over the sticky footer, so the footer showed through from the click until the next page arrived. Now only `#swup`'s children fade (`animationSelector: '#swup > *'`; `transition-fade` is gone from `page.html.twig`): out in 0.2s, in over 0.35s with a 0.5rem rise (`base/_swup.scss`), skipped for anything holding a fixed layer (landing video, TOC button) and under reduced motion. Links Swup would follow are prefetched into its cache on hover (80ms), focus or touch, two at a time, never with Data Saver or on 2G, and a click during a prefetch reuses that request. Why: the blank page and the footer flash were disorienting.

- **Visitor parking guides: Main Library and Hill Memorial Library.** New `scripts/palefire_guide_parking.php` builds two unpublished guide pages from LSU Parking & Transportation Services' visitor, ADA and Union Square Garage pages (checked 2026-10-26) and the Special Collections travel page: pay-to-park ($3.00 an hour, weekdays 7 a.m.–8 p.m., card only), the three nearest visitor lots in a table, the $12 visitor day permit, accessible parking with a state placard, common questions, and contacts. The two share their Resources and most blocks; Hill's adds the reading-room first step, its address and the Special Collections desks, and each lot's name in its table links to that lot in Google Maps (`api=1` search links, each checked to open on the one lot). What neither source states (evenings and weekends, game days, entrances, the Main Library's address and walk times) is left as "[Editor: …]". Why: visitors without an LSU permit ask where to park more than anything else before a first visit.
- **Landing hours badge: no glass, so the header no longer flickers.** `_landing-features.scss` (`.pf-landing__hours-label`, the "Open now" badge under the hero title): its `backdrop-filter` is gone. A 26px blur on a 24px-tall badge, under the header's own glass, made the frosted header flicker as the badge scrolled beneath it; open and closing soon, its gold ground was solid anyway. The closed and loading plate is now 90% of the page color (was 65%) to make up for the lost blur: over the hero's 80% wash on a white video frame, its text is 4.5:1 and up on every swatch (lowest: Solarized 4.51; 65% without the blur would fall to 3.87). Why: the header's glass should be the only blur over anything that scrolls beneath it.
- **Landing pages: alternating section tones.** `_sections.scss` (Alternating tones on a landing page): on any page whose sections start with a landing hero, every other band takes the swatch's surface color (`--pf-g-surface`, 1.04–1.14:1 off the page): the Quick Start guide, then the even sections after the landing (on /v2/special: Go deeper, and the events and blog). The Visit strip and its map stay the page color. Inside a band, anything that used the surface color (guide panels, imageless tiles, Bootstrap's secondary and tertiary backgrounds) takes the page color, so it still stands off. The Quick Start sits in the landing's 1280px column, so its band runs edge to edge on a clipped box shadow, starting halfway down the guide's top space, clear of the hero cards' labels; nothing moves. Text, muted text and accents keep 4.5:1 on the surface on every swatch, high contrast included (lowest: Solarized text 4.57, Lavender muted 4.61). Checked on /v2/special in Twilight and Cream at 1440, and at 320: no horizontal scroll. Why: the parts of a long landing read apart without patterns or images, in keeping with the theme's Swiss restraint.
- **Hero banners: titles stop at three lines again.** In the caption layout (`pf-banner-caption`, `_landing-features.scss`) the title's scrim padding included the space under the words, and `overflow: hidden` clips at the padding box, so a fourth line the clamp hid still showed in that padding ("Williams Center" on the blog banner). That space is now a transparent bottom border of the same size (`--pf-banner-pad`), with the scrim painted under it (`background-origin: border-box`). Checked on /v2/special: three lines with an ellipsis at 1440 and 1024; the blog line under 44rem keeps its two; no horizontal scroll at 320, 768, 1024 or 1440.
- **Monospace sizes: 12px labels, 13px controls.** Two tokens on `:root` (`base/_base.scss`): `--pf-mono-label` (.75rem) and `--pf-mono-ui` (.8125rem), now used by every monospace size in the theme (17 files) in place of the 10px, 11px and 12px literals. Controls take the 13px size: the hero Search button and links, the Quick Start buttons, the landing search, back to top, and the exhibition, viewer and parish buttons and toggles. At 13px the hero and Quick Start tracking is .12em (was .16em). Quick Start wrap points moved to the wider labels: one line from 416px (was 397px), one column at 320px and under (was 302px). On phones the breadcrumb tracking is 0.06em (was 0.1em), so "Special Collections" still fits at 320px. Checked on /v2/special at 320, 768, 1024 and 1440 with local CSS: no horizontal scroll, and no monospace text under 12px apart from the tile thumbnails' scaled outline drawing. Why: 10px and 11px Consolas, uppercase and tracked, was hard to read, especially on buttons.
- **Exhibitions: a placard for the next gallery.** `js/exhibition.js` (`addNext()`), `_exhibition.scss` (`.pf-exhibit-next`): every gallery but the last ends with a hairline, "Next · 02 / 07" in the mono label voice (the number in the highlight), the next gallery's title and an arrow pointing down. Scrolling on is still the way through; the placard is a signpost, and as a link it glides to the next gallery and focuses its heading. After the section's content box, so it sits on the page color past any pinned image, lined up with the text column. Screen readers hear "Next 02 / 07: Audubon at Oakley". Hover underlines the title and nudges the arrow down (not with reduced motion). Checked on Audubon (6 placards for 7 galleries) and Occult (10 for 11) at 1440 and 320: no horizontal scroll; a click lands the next gallery under the header. Why: every gallery now ends the same way, so the seam no longer depends on how much room its last text leaves.

- **Exhibitions: no curtain on the next gallery's image.** `_exhibition.scss`: the scroll-linked curtain on each gallery's opening image (stage, cover frame, lead band) is out; the handoff keeps only the outgoing pinned image's fade, so the next gallery's image simply scrolls in. Checked on Audubon, galleries 1 to 2: the incoming image is never clipped, and the outgoing one is still gone once the next gallery's top is 40% up the screen. Why: one quiet motion at a gallery's edge is enough, and readers move through the exhibition by scrolling, as on the rest of the site.

- **Exhibitions: a handoff between galleries.** `_exhibition.scss`: each gallery's opening image (the pinned stage, a cover's frame, a lead band) now unrolls like a curtain as it scrolls in (from its top a fifth of the way up the screen until it is all in view), and a pinned one fades out as it scrolls away (gone by 70% of its exit), on the page's scroll rather than on load. On Audubon, galleries 1 to 2: the outgoing image is gone once the next gallery's top is 40% up the screen, and the incoming one is fully open as its top reaches the header. Why: in one scrolled document the outgoing image scrolled off half on screen while the next came up under it. Not scroll snap: on long texts a snap pulls the reader past a gallery's last lines, and it fights the pinned openings' pan. Reduced motion: no handoff; browsers without scroll-driven animation keep the curtain on load.

- **LF line endings everywhere.** `.gitattributes` (`* text=auto eol=lf`, binaries left alone) and `.editorconfig` (LF, UTF-8, final newline, two-space indents). The repository already stored every file in LF, but Git for Windows' system-wide `core.autocrlf=true` had left 46 working copies in CRLF, so editing one line showed the whole file as changed. Why: one line-ending rule for every editor and checkout.

- **Page transitions load missing libraries instead of reloading.** `js/palefire-swup.js` (Missing assets): when the next page needs libraries this one hasn't loaded, they're added before the swap, as an aggregate of only those libraries from Drupal's asset route (`include` = the next page's libraries less this one's, `exclude` = this one's, dependencies resolved by Drupal, so core never runs twice). Each group is fetched and added in order (CSS, header scripts, footer scripts); files outside aggregates are added as they are; `ajaxPageState.libraries` becomes the union. Before, such a visit faded out, fetched the page, then reloaded it in full: most links from /v2/special did. A full load stays the fallback if anything fails, or for pages with inline scripts in their content; exhibitions still load normally. Checked on lib.lsu.edu: /v2/special to Staff Favorites, the exhibitions list, Archives and back, all swapped in place with no errors; Staff Favorites to /v2/special loads the ten landing behaviors (two script groups, 4 and 55 KB) and the video, next event, hours, map, TOC and tile reveals match a direct load. One run in four fell back to a full load (the safety net).

- **Exhibitions load normally; a loading line for slow page changes.**
  - `js/palefire-swup.js`: links to an exhibition (`/…/exhibitions/<name>`, not the list) skip Swup. Exhibitions need scripts other pages don't load, so Swup faded the page out, fetched the exhibition, found it incompatible and loaded it a second time, with nothing on screen in between: why Audubon looked broken from /v2/special. Any other page that falls back to a full load is remembered for the session (`sessionStorage`) and loaded normally from the start next time.
  - Loading line (`.pf-loading`, `_header.scss`): 2px at the header's foot in the swatch's highlight, a short bar sliding across, while the next page loads, a Swup visit or a normal one (a same-site link Swup skips; not new tabs, downloads or same-page anchors). Hidden for the first 300ms, so quick visits never flash it; after a second screen readers hear "Loading…". Reduced motion: no slide, the line breathes. Cleared when a page comes back from the back/forward cache. Checked on lib.lsu.edu: the Audubon link now bypasses Swup, and the line is hidden at the click and shows, sliding, by 500ms.

- **A softer top wash; banner categories under the picture; shorter Quick Start buttons; smooth hatching.**
  - Cover (`_landing-features.scss`, `pf-cover-top-wash()`): the wash under the clear header and bar keeps 70% behind their words (4.5:1 for white over a white frame) but no longer stops dead 1.5rem below the bar, which read as a band: it eases out (a cosine) over the next 12rem.
  - Hero banners: the category is a caption under the picture, in the section's colors and the mono label voice, as on the tile showcase's Grid view; the title stays on the picture over a scrim of its own (85% black at the foot). The picture is its own box (`--pf-banner-ratio`, `--pf-banner-shadow`). Under 44rem the blog post stays a line of text.
  - Quick Start: "Order prices" is "Prices" and "See hours" is "Hours". The buttons' wrap points follow the shorter labels (one line from 396px, was 474px; one column under 302px, was 318px).
  - Hatching (`base/_mixins.scss`, `pf-hatch()`): the 1px diagonal lines (Visit's "Show map", the hero event's waiting square, From the stacks, placeholder frames) and the Hours drawer's closed-day stripes get half-pixel soft edges. Hard gradient stops aren't anti-aliased, so 45-degree lines came out uneven, worst at 125% and 150% zoom.

- **Hero cover: the video holds still until the Quick Start.** The cover's video is pinned (sticky, a screen tall) while the title, search and banners scroll up over it, and leaves with the banners as "01" arrives, fading as before. The media layer now spans the banners' row too, and the first screen's height it used to set is kept by a spacer (the lead's `::before`), so nothing on the first screen moves (checked at 1440, 1024, 768 and 320: every block where it was, no horizontal scroll). The washes stay with the words they serve; from lg the bottom fade sits under the banners at fixed depths (solid 2rem, gone by 28rem). How long the video holds depends on the screen: 334px of scrolling at 1440×900, little at 1024×900, where the banners already end at the fold. Reduced motion and Settings > Reduce motion keep the band that simply scrolls. Why: more time with the video.

- **Clear header: no shadows, and the cover's top wash only as deep as the header and bar.** While the header is clear it has no drop shadow, and neither it nor the bar has the page-color halo round its words, logo and icons (they read as smudges over the video); the glass and shadow come back once the page scrolls. The cover's top wash, which faded out over about a quarter of the screen (about 260px at 1440), now covers the header and the bar (70%, which keeps their white words at 4.5:1 over a white frame) and is gone 1.5rem below them (105px at 1440).

- **Landing bar: the breadcrumb and TOC button back on the left.** The bar is as it was before today: the TOC button over the logo's column, the breadcrumb starting under the menu's first item, the video (or zoom) button at the far end. `js/modular-toc-collapse.js` no longer adds a copy of the button to the bar. The clear header over background videos and images stays.

- **Header: the logo back to its size; clear over any background video or image.**
  - The logo no longer grows across the bar (it read badly); the header is as before, with the bar's breadcrumb and TOC button still on the right.
  - Clear header (`_landing-features.scss`): no glass, fill or rule at the top of the page whenever the first section has a landing background video (the hero's cover among them) or a background image, not only on the cover; glass again once scrolled. The cover's video layer now reaches up under the header (it stopped at the bar, so the clear header showed the plain page color), and its top wash is lighter (72% to 70% over the first 10%, was 88% to 80%), the least that keeps the header's and bar's white words at 4.5:1 over a white frame, so the video shows through. Why: the header still read as a filled bar over the video.

- **The logo across the landing bar, a clear header on the cover, level hero banners.**
  - Logo and bar (`_landing-features.scss`, `js/modular-toc-collapse.js`): from lg, on a page with the landing's sub-header bar (landings, an exhibition's title page), while the page is at its top (no `.pf-bar-stuck`), the logo is a third larger and centred on the header and the bar together, with no rule between them. It eases back into the header's row once the page scrolls (a transform, so no layout shift; none with reduced motion). The breadcrumb and a copy of the TOC's "show" button (`.pf-landing__toc`, added by the script while the TOC is hidden) sit at the bar's right end, before the video or zoom button; the floating TOC button over the logo's column is gone there. Pressing either TOC button moves focus to the one that replaces it. The breadcrumb list's own room for the video button is dropped inside the bar, which already keeps it. Below lg nothing changes.
  - Clear header (`_landing-features.scss`): on the hero's cover, at the top of the page, the header has no glass, fill or rule, like the bar; its words keep a halo of the page color, and the logo and icons a matching drop shadow, for contrast over the video. Glass again once scrolled; reduce transparency keeps it throughout.
  - Hero banners: no vertical stagger. Every banner stands level (no drop on the second and fourth, or on the right column below 44rem), the grid loses the room it kept under the drop, and the rise-in comes up from below for all of them, still left to right.
  - Checked on lib.lsu.edu with the local CSS and scripts at 1440, 1024, 768 and 320: no horizontal scroll; header and bar change state on scroll.

- **Hero banners: the tint is the page's own color.** Each picture is grey (`contrast(1.1)`) with the cover's page color (`--pf-cover-bg`, so the swatch's) washed over it at 50%, in place of the accent multiplied over it, so the row sinks into the dark ground around it until a banner is hovered or focused. The scrim and the white words are unchanged. Why: the gold read as a separate color on the page rather than part of it.
- **Exhibitions as one scrolled document; still hero banners, toned until hover; tiles open on Grid with the label under the picture.**
  - Exhibitions (`js/exhibition.js`, `_exhibition.scss`, `exhibit-cover.html.twig`): every gallery shows, one after another under the title page, with a hairline between them. Gone: hiding all but one gallery, the fade between galleries, the previous / next links, the folded running head and its breadcrumb crumb, and the history entries per gallery. "Begin" and in-text `#links` glide and focus; TOC links to a wall's entries still open the object view. The pinned opening's fade and pan moved from the page's scroll (which assumed the gallery opened at the top) to the gallery's own view timeline, inset by the header; measured on Occult, the stage pins at 0 and the pan ends exactly at 40svh plus `--pf-pan`, as before. The bar's zoom button now opens only the title page's cover, so a gallery's opening shows its own enlarge mark again. Why: the exhibition reads as one document by default.
  - Hero banners (`_landing-features.scss`): no more scroll drift or cross (the row and its stagger hold still); the pictures still pan in their frames. Each picture is grey and a little dimmed (`brightness(.8) contrast(1.1)`), with the swatch's accent multiplied over it (lifted with a third white, so Cream's purple tints rather than blacks out); multiply only darkens, so the white words keep or gain contrast. Hover or focus fades its own colors back in as it zooms. Why: the row reads as one set in the page's colors, and the color reveal rewards hover.
  - Tile showcase, masonry (`paragraph--tile-showcase.html.twig`, `js/tile-views.js`, `_tile-showcase.scss`): Grid is the first and default view; the old "Default" is now "Masonry". A pick saved as "default" is no longer a view, so those visitors start on Grid too. In Grid the Category label sits under the picture as a mono caption in the section's colors (the title stays on the picture); the scrim and shadow are on the picture. Masonry and List are unchanged.

### 2026-10-23

- **Rooms and access tidied; See hours button; tiles fill to the foot; one label per card.**
  - Rooms and access (`map-key.html.twig`, `_landing-swiss.scss`, `_landing-features.scss`): no hairline under the drawing (03's rule opens the section), no elevator mark in its first column for now (the drawing keeps it), no closing line under each column's last room, and more room round the rule before Visit (2–3rem above, 1–1.5rem below; it was 20px and 14px).
  - Quick Start: the hours button reads "● See hours ↓" (status words for screen readers only, so the dot's color never carries it alone). When the three buttons don't fit one line (about 474px), a container query lays them out as two columns the width of Full guide and Order prices with See hours across both, its edges on theirs; under about 318px, one column at one width. Breakpoints are in rem from the measured labels; a much longer translation would need them moved.
  - Tiles (`_tile-showcase.scss`): the banner-voice cards' picture stopped 2px above the foot, the same strip of grey ground that read as a border on the hero banners; it now fills to the foot.
  - Labels (`palefire.theme`, `paragraph--tile-showcase.html.twig`): tiles show one label for their Category terms, as the hero banners do, gold on hover in the banners' highlight. `_palefire_card_label()` joins the terms with " + ", or a space when one is Exhibition ("Digital Exhibition", "Physical Exhibition"), for the banners, tiles and From the stacks alike.
  - Checked at 320, 400 and 1440 on the live page with this markup and CSS swapped in: no horizontal scroll; See hours spans exactly the pair above at 400 (20–338px) and all three are one width at 320. The PHP wasn't run locally.

- **03 Rooms and access: the map's legend as an indexed section; index numbers no longer typed by hand.**
  - Why: in the hero's Visit panel the legend's toggle ("Legend & spaces", bold sans capitals, a heavy chevron) was the one row on the page outside the mono label voice, it started collapsed across the full width, and its note floated alone between two rules.
  - `map-key.html.twig`, `_landing-swiss.scss`, `building-map.js`: in the hero (`map.visit`) the toggle is an index rule, "03 Rooms and access", with the count and +/− at its right end in the rule link's voice; it stays open, and the body is an index in three columns from 768px (the note and legend, then Walk in and By appointment, group headings as grey mono labels, rooms in white), stacked below. Maps elsewhere keep their own key.
  - Numbering (`palefire.theme`, `landing.html.twig`, the scroll-section field template): `_palefire_hero_rules()` lists the hero's rules from the facts its templates read (lead shown, published map, Visit card); the hero numbers them from it (`hero_index`, and `map.visit.index` for the key) and the sections after count on from its length, so the Tile showcases are now 04 and 05. The literal '01' and '02' are gone. A CSS counter was tried first: the map and the cover are size containers, which scope counters, so the key read 01.
  - Checked on the live page with this markup and CSS swapped in at 400, 768, 1024 and 1440: 01 to 05 in order, no horizontal scroll, group headings grey (they are h4s, which Twilight's content-heading gold reached). The PHP wasn't run locally (no PHP here); after `drush cr`, the hero should read 01, 02, then 03 Rooms and access, then 04 and 05.

- **Twilight text in two tones; hours links, badge and banners tidied.**
  - Note: the SCSS for the Twilight colors, the search focus fix and the white Stacks titles went out early inside 1b28303 (its `git add scss/components` picked them up mid-edit). This entry covers them and finishes the rest: the footer links, section titles and templates.
  - Twilight (`_reading-themes.scss`): running text is now the soft grey the labels use (`#b7b3ba`, 7.4:1 on the page, 6.7:1 on surfaces; was near-white `#edebee`); headings (`--bs-heading-color`), links and bold (a `strong` key) keep near-white. The grey can't go dimmer for a third tier: over a white video frame it is already near 4.5:1. Checked by rendering the page with text hidden and measuring the brightest pixel under every grey text box at 320 and 1440 against the current colors: no new failures (lowest 5.1:1, median 7.5:1). Footer links, landing section titles, the index-rule links and the Stacks titles take the heading color; the Quick Start's intro and notes take the grey (the video is fully washed out behind it).
  - Hero hours (`landing.html.twig`, `_landing-features.scss`): today's hours are the link to the week (the separate "This week's hours" is gone; its name finishes "this week's hours" for screen readers). The status badge loses its frame for a solid ground: the swatch's accent while open or closing soon (black on pale gold), the hero links' plate while closed or loading; a frame only in forced colors.
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
  - `_landing-features.scss`: hovering or focusing the hero's search draws a 2px line in the Search button's gold along the field's foot, from the start to the button (the input's own background, so it ends at the button at every width; mirrored in RTL), and the placeholder brightens toward the text color. With reduced motion (or the Settings switch) the line appears at once. Why: the search needed a cue on hover; the line ties the field to its gold button without moving anything. Checked at 1440 on the live page with this CSS injected.

- **Hero search: placeholder in the intro's type.**
  - `_landing-features.scss`: the hero's search field (placeholder and typed words) takes the intro's size (`--pf-hero-text`) and regular weight instead of the larger 300 the other landings' fields use. Why: the light weight was the one 300 on the hero and read out of step with the intro beside it.

- **TOC icon: the rail and its marker.**
  - `page.html.twig`, `_modular.scss`: the list icon (TOC toggle and phone button) redrawn as the sidebar looks: the rail's top third in the bright text color (`--pf-toc-active`, as the TOC's active link; `.pf-toc-icon__mark`), the rest at 35%, then three entries left-aligned with ragged ends, all at one 1.5px weight. Why: the previous five-part 1px drawing looked thin and busy at 16px. On hover the bars brighten to match the marker.

- **Hero spacing restored; search shadow; TOC icon redrawn.**
  - `_landing-features.scss`: the hero's top block (a grid from 40rem since the links moved beside the intro) took the older `xl` rule's 2rem row gap, which stacked 32px more under the title and under the hours. The row gap is now 0, so their own margins space them as before. The hero's search (field and button) has the banners' soft shadow; focus keeps its gold ring over it.
  - `page.html.twig`: the list icon (TOC toggle and phone button) on whole pixels: a faint rail, a heading line, then three entries indented and left-aligned with ragged ends, four rows 2px apart, 1px lines as the header's icons, the drawing centred in its box. Why: the old icon's 1.2-unit bars blurred at 16px, its rows were uneven and its short bars were right-aligned, unlike a TOC.

- **Hero links: glass plate, flush cells, gold hover; Log in over the Search button.**
  - `_landing-features.scss`: the links' plate takes the glass blur again (still 65%, lighter than the search's 80%), so the words read over busy frames. From 80rem the three cells are flush (no gutters): the last is one banner wide, the others a banner and a gutter, so a hover fills a link's whole cell, the hairlines sit on the cells' edges, and Log in's cell and words start where the Search button's do (1130px and 1150px at 1440, both). Hover turns the words gold over the deeper cell (about 82% in all: 5.9:1 for gold over a white frame).
  - Why: hovers filled only part of a column (the gutter stayed light), Log in sat a gutter left of the Search button, and the plate alone was hard to read over bright frames. Checked at 1440 on the live page with this CSS injected.

- **Hero links on one lighter plate; landing bar lines up with the header; map entrance as an opening.**
  - `_landing-features.scss`: University Archives, Oral History and Log in share one plate split by hairlines, beside the intro and as tall as it (a grid row from 40rem: rows in the right half; from 80rem three columns over banners 3-5), words and arrows centred. Phones: rows under the intro. The plate is the page color at 65%, no glass: lighter than the search's 80% so the two read apart, and the least that keeps white words at 4.5:1 over a white frame (4.7:1). Log in's words go white (gold would need 75%); its arrow keeps the gold. Hover deepens a link's share of the plate. Why: the plateless index was hard to read over bright frames.
  - `_header.scss`, `_modular.scss`, `_landing-features.scss` (lg and up, where the menu shows): the landing bar's TOC button is as wide as the logo (`--pf-logo-w`, which follows the compact header), its icon on the logo's edge, with a clear 1rem strip under it that also takes clicks; the breadcrumb starts where "Help with" does (gutter + logo + `$pf-brand-gap`). Hover brightens the icon instead of filling the wider box.
  - `hill-memorial-map.svg.twig`, `building-map.js`, `_building-map.scss`: the front wall stops either side of the doorway, each end closed by a short edge, instead of the porch jutting out; the entrance arrow and its centring script are gone.

- **Guides: room between the breadcrumb and the title.**
  - `_guide.scss`: on guides the `<h1>` sits `clamp(2rem, 1rem + 3vw, 3.5rem)` under the breadcrumb (was 0.85rem), more than the 1rem between the title and the intro. A guide with no breadcrumb keeps its title on the first line.
  - Why: the breadcrumb is a running head; set tight over the title it read as part of it. The sticky landing bar was considered and left to landings: a reading page needs no chrome pinned over it.

- **Hero links: a ruled index beside the intro; the search loses its frame.**
  - `_landing-features.scss`: University Archives, Oral History and Log in drop their framed glass plates and become a hairline index (mono label, arrow, rule gold on hover and focus). Phones: rows under the intro, a rule only between them. From 40rem: the same rows in the intro's right half (a two-column grid, both feet level, so they can't run into the hours). From 80rem: three columns over banners 3-5, a rule on top of each, Log in over the Search button. The words carry the hours line's halo for 4.5:1 on any video frame.
  - The hero's search field has no frame (clear; gold on focus, CanvasText in forced colors): its fill and the Search button mark it, and no rule sits under the links to meet it.
  - Why: the plates matched the search field's look, so the links read as more fields of one form, and the row under the intro left empty space. Checked in headless Edge against the live page with local CSS at 320, 768, 1024 and 1440; no horizontal scroll.

- **Price list fixes: the prose width actually applies; no empty line in the fee.**
  - `_guide.scss`: the price list's block test was `:has(table:not(:has(…)))`, and `:has()` can't nest, so browsers dropped the rule and the list (and the text after it) ran to the guide's 52rem. Now `:has(table):not(:has(thead, tr > :nth-child(3)))`: 672px, the intro's width.
  - The fee's label is a block, so the `<br>` after it left an empty line over "Processing fee"; that `<br>` is hidden.
  - Checked on the live page with this CSS injected in Edge at 1568 wide. No content change: no need to rerun the prices script.

- **Price list: the processing fee as the list's foot; notes muted; prose width.**
  - `palefire_guide_duplication_prices.html`: the fee leaves the rows for a `<tfoot>` ("Added to every order", Processing fee, + $10), and the caption becomes "Prices per item". Notes under an item are `<em>`, since the guide's text format strips `<small>` (live, the notes showed at full size and color). The script now warns if the format strips `<tfoot>` or `<em>`.
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
  - Verified in Edge with local CSS: sticky top 105px at 1280×1000; outline colors sampled every 150ms; two labels on AVES at 1440. A second term only shows once it's on the node's published revision and the term is published: live AVES currently lists only "Exhibition".

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

- **Request paths (Quick Start Guide): the whole step opens its panel.** On a stop with a "how" panel, the label's button now stretches over the whole stop (icon, label and note) plus half a rem on every side (`.pf-paths__peek::after`), and its focus ring goes round that area. Hovering or focusing it turns the diamond's outline the accent color and brings the icon's second duotone layer up from 40% to 75%. The button's accessible name is unchanged, and stops without a panel are unchanged.
  - Why: only the label text was clickable, a small target next to an icon that looked clickable.

- **Tile showcase: a lighter scrim over no-cover tiles.** Cards that draw their page (`.pf-tile--no-image`) get their own `--pf-tile-scrim`: 55% black up to the title's full height (`--pf-scrim-floor`: 6.5rem on masonry banners, whose titles clamp at three lines; 9rem on stagger and grid, whose titles don't), then easing out over the next 9.5rem. Photo tiles keep the old scrim.
  - Why: the outline's colors are known, so it needs less than a photo. White on the lightest of them (#f8f9fa, the plain light page, with the blurred text counted at full strength) needs 52% black for 4.5:1; light swatches need 47–50%, dark ones 35–44%. 55% leaves a margin.

- **Tile showcase: the no-cover outline is closer and panned right.** The angled page is at 85% (was 70%) and shifted so the "On this page" rail runs half off the card's left edge, and the title and column are cut off at the right.
  - Why: at 70% the page filled only the top of the card, and the full title still read as a repeat; closer in, the crop keeps a hint of the rail and only part of the title.

- **Tile showcase: the no-cover outline is photographed on a desk, title back.** The blurred desktop page now sits tipped away and turned 14° (`rotateX` / `rotateZ` in perspective), zoomed out to 70% so the whole rail and column show with words still cut off at the right edge, under one flat blur. The page's title is back at the top of its column.
  - Why: at an angle the blurred title reads as part of a photographed page rather than a copy of the card's title, and the angle gives the cards depth. One flat blur instead of a depth-of-field stack keeps it to a single filter per card.

- **Tile showcase: the no-cover outline is the desktop page, without the title.** The blurred page now shows the guide as a desktop does, anchored left: the "On this page" rail (its sections, the gold bar on the first), then the intro and sections in a wide column that the card's edge cuts off. The outline's own title is gone. On hover only the content column scrolls; the rail stays, as the real one is sticky.
  - Why: the blurred title repeated the card's title right below it, and the narrow single column read as a phone page.

- **Tile showcase: a card with no cover shows its page, blurred.** Instead of a blank dark card, a tile with no image (most guides) draws the page it links to: its title, intro and first four titled sections with their block titles and opening lines (`_palefire_page_outline()`), set small, blurred and aria-hidden, in the reading swatch's colors (`--pf-g-*`; the section's Bootstrap colors on pages without one). It scales with the card, so the masonry's List thumbnail is the same page in miniature, and drifts up slightly on hover (not with reduced motion). Hidden in forced colors. Every layout but the index, which has no pictures.
  - Why: guides almost never have cover images, and an empty card says nothing about what's inside.

- **Exhibition covers: the long fade is back, with the image still moved right.** From lg the cover image starts at 28% of the head (`$pf-exhibit-cover-media`), and the side wash is solid to 38% and fades out by 92%. The entry below had the image at 44% under a 46% to 84% fade: the subject showed, but nothing of the image was left behind the title, so the head looked cut in two with a flat block of color beside the picture. Now the image shows faintly behind the title as before (the text gets about the same wash), and a portrait image's centre sits about 14% further right than it did originally.
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
  label (dot and small capitals in a frame in the state's color), then
  today's hours and the building in the text face; no "All hours" link.
  The banners are one row of five from 44rem of landing (titles and
  padding scale with it); narrower, the blog post is a line of text and
  the rest go two by two with the right column lower (4:5 on tablets). On
  the full-screen cover they rise 9rem into the video with a soft shadow
  (the band runs 1rem past the fold, their tops 8rem above it), and a
  scroll-driven parallax closes their offsets as the page scrolls (off
  with reduced motion). Light swatches get the header inverted: the
  swatch's heading color as its glass, the page color for its text and
  icons, the dropdowns to match. On phones the Quick Start folds to its
  first option under a fade, with "Show all steps" (js/palefire.js;
  focus inside unfolds it too). Why: sign-in belongs to requests, not the
  site; mono is for labels; the banners needed depth; the all-light frame
  glared.
- **The hero's bar as a clear caption strip.** On the hero cover the
  sub-header bar has no glass at the top of the page: the breadcrumb,
  Sign in and pause sit straight on the video (a firmer top wash under
  them, a halo of the page color round each word), so the header is the
  page's one bar. Once the page scrolls and the bar sticks, it turns to
  glass (js/palefire.js marks `<html>` `.pf-bar-stuck`; a .3s fade).
  Reduce transparency keeps the glass. Measured against the pixels behind
  each word: 7:1 or more on Twilight, Cream, Sepia and Sage (Sign in was
  3.0:1 on Sepia before the firmer wash). Why: two near-equal glass bars
  read as two headers.
- **Hours line at AA over the video.** On the cover the hours line takes
  the text's full color (today's hours and the building at 500) with a
  soft halo of the page color. Measured against the pixels behind each
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
  between them, each after a small diamond in the highlight color. Why:
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
  `accent`) colors the page title, the index numbers and the Quick Start
  letters, so every swatch marks them as Twilight does (Cream: LSU
  purple). Why: the banners lead from the cover into the page with real
  collection imagery, and only Twilight and Material had a title color.
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
  paragraph tone (it set its own color). The map tooltip is square with a
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
  description and the Quick Guide intro are set at 80% of the text color
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
  weight and tracking, in the text color); gold kept for the page title,
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
  the title, intro and search, washed into the page color as on exhibition
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
  glass and colors, so it follows the reading colors. Why: review on
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
  icon in a 32px hairline box, then the title in the text color with the
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
  little to the page. It takes the accent color, and its light layer
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
  `pf-eased-wash()` in the default swatch's page color. In the "Cover
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
  scrolls, edge to edge on the page color, and from lg it lines up with
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
  image now fades into the page color over the foot (4–8rem), and the
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
  of the page color below lg; from lg the folded intro shows as many lines
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
  page-color washes over their images fade along an ease-in-out curve in
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
  it on the page color, so the text moves and the image stays put.
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

- Twilight becomes the default reading color; one hover timing everywhere.
- Mobile header and menu redesigned as a full-screen drawer with toggles,
  quick links, text size and reading colors; drawers open over it.
- Landing hero paragraph; Next event card.
- Cache busting fixed; guide accordions and dropdowns animate calmly; TOC
  activation at three-quarters down.

### 2026-10-16

- Guide pages (content model, scripts, guide theme); reading colors
  everywhere; drawers in the guides' style; cache busting.
- Landing layout: lead panel, Visit strip, map-only option, Swiss layer,
  "Light, dark at the map"; events carousel Swiss styling.
