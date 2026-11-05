# Palefire style guide: working notes

The style guide is a public exhibition page at
**/v2/special/internal/styleguide**. These notes are its source: what it
covers, the rules behind it, and what to update when a component is added or
changed. Keep them in step with the page.

## How it is built

| Part | File | Owns |
| --- | --- | --- |
| Page content | `scripts/palefire_styleguide.php` | Galleries, prose, editor notes, live block examples. Re-running it rebuilds the page as a new revision. Edits made in the UI are lost on the next run. |
| Plates | `templates/includes/styleguide-plate.html.twig` | The Behance-style sheets: overview, colors, type, grid, spacing, lines, motion. |
| Plate styles | `scss/styleguide.scss` → `css/styleguide.css` | Its own entry point and library (`palefire/styleguide`), so only this page loads it. |
| Plate script | `js/styleguide.js` | Prints hex codes, contrast and easing curves from what the browser draws. |
| Marker | `palefire_preprocess_paragraph__guide_text()` in `palefire.theme` | Any text block whose paragraph is only `[styleguide:<plate>]` draws that plate after its text. Unknown names stay as typed. |

Plates never hard-code a token's value. Sizes, breakpoints and spacers are
printed from Sass (`content:`). Colors and curves are read in the browser. A
token change therefore shows up on the page after `npm run build` and a
deploy, with no content edit. The colour plate repaints when a visitor picks
another reading colour.

Values that live in a rule rather than a token are mirrored in
`styleguide.scss`, with a comment naming their source:

- page title size (`_landing-swiss.scss`)
- gallery title and reading text (`_exhibition.scss`)
- settle and curtain durations (`_exhibition.scss`)

If one of those changes, update its mirror. Better still, promote it to a
token in `_variables.scss`, as was done for `$pf-exhibit-ease` and
`$pf-exhibit-curtain`.

## When a component is added or changed

1. **Notes:** add or update its row in the inventory below, and its rules if
   it sets a new one.
2. **Page:** if editors can add it, add a live example and a "For editors"
   callout to the "Exhibition blocks" gallery (or a new gallery) in
   `palefire_styleguide.php`. Guard new paragraph types with
   `ParagraphsType::load()` so the script still runs where the type doesn't
   exist yet.
3. **Plate:** if it introduces a token (colour, size, easing), add it to the
   plate that shows that kind of token. For a new plate:
   - append its name to `PALEFIRE_STYLEGUIDE_PLATES` (at the end, so the
     catalogue numbers of existing plates stay the same);
   - add a branch to the plate template and a title to `titles`;
   - style it in `styleguide.scss`;
   - bump "Plates: N" in the script.
4. **Build:** run `npm run build:css`, which compiles both stylesheets.
5. **Production:** after `git pull` and `drush cr`, run
   `drush php:script scripts/palefire_styleguide.php` (add `image=<media id>`
   for the figure examples). Then run `drush cr` again.

## Rules (the short version)

- **Swiss, museum-calm.** Hierarchy comes from type, size and space, not
  boxes. Colour is kept for meaning.
- **Colour:**
  - Use tokens only. Components read `--pf-g-*` (reading colours) or the
    section tokens, never literals.
  - LSU gold appears only on purple or dark grounds.
  - Photographs: colour for what people look at (the cover, the hero
    banners), monochrome for a backdrop behind data (the location top).
    Both are pulled toward the swatch with the page colour in the `color`
    blend, which keeps lightness, so it never moves a contrast. Grey on a
    banner means "not this one", so a cover never goes fully grey.
- **Lines:** every rule, row and frame is `$pf-hairline` (20%). A button with
  words has no line: its plate and its label identify it. Fields and wordless
  buttons keep `--pf-section-border` at 3:1.
- **Type:**
  - Roboto everywhere.
  - Playfair Display for exhibition titles only, at display sizes.
  - Inside a block with a heading, use Heading 4: an h3 becomes a TOC link.
- **Motion:**
  - Easing curves come from `_variables.scss`.
  - Colour arrives fast (`$pf-hover-in`) and leaves slowly (`$pf-hover-out`).
  - Decorative motion sits behind `prefers-reduced-motion: no-preference`
    and `html:not(.pf-a11y-motion)`.
  - Nothing waits hidden. Page titles (the LCP) only move, at full opacity
    from the first frame; scroll reveals start at `$pf-reveal-from` (.85),
    never 0.
- **Accessibility:**
  - WCAG 2.2 AA in every reading colour.
  - Visible `:focus-visible` ring (3px, 2px offset).
  - Targets of 44px, or 36px in the compact header.
- **Numbering:** letters for alternatives (the Quick Start's A, B, C),
  1, 2, 3 for steps done in order (`pf-steps`, no leading zero), and
  01, 02 only on section index rules, with 01.1, 01.2 for a hero's tabs. On a
  location page the rule
  carries the number alone: its sticky heading names the part. A walk-in
  room's parts have no number; their rule shows only with its link.
- **Icons:** Font Awesome 5.15.4 class names only (`fas`, `far`, `fab`),
  with `aria-hidden="true"`. The theme mostly inlines SVGs.
- **Performance:**
  - Attach a library only where it is needed.
  - Lazy-load images and give them width and height.
  - No new external dependencies without asking.
- **Page kinds:** `<html data-pf-page>` names the kind of Modular page
  (`PalefireHooks::pageKind()`). Scope page-wide rules with `$pf-system`
  (column, grid, section headings) or `$pf-banded` (alternating tones),
  not with a page's own class. Guides, forms and exhibitions keep their
  own layouts; guides read the shared type tokens.

## Component inventory

On page: ✔ shown live · ▣ shown as a plate · – not yet.

| Component | Template / styles | On page |
| --- | --- | --- |
| Reading colours, brand colours | `_reading-themes.scss`, `_variables.scss` | ▣ Colour |
| Guide blocks: Flow chart, Status board, Figures, Code | `paragraph--guide-flow`, `-guide-status-board`, `-guide-stats`, `-guide-code`, `guide-blocks.scss` (library `palefire/guide-blocks`) | ✔ Guide blocks |
| Type scale, fonts | `_variables.scss`, `_fonts.scss` | ▣ Typography |
| Grid, breakpoints, measures | Bootstrap 5.3, `_variables.scss` | ▣ Grid |
| Spacers, rhythm | Bootstrap `$spacers` | ▣ Spacing |
| Hairline, control border, focus ring | `_base.scss`, `_variables.scss` | ▣ Lines |
| Motion tokens | `_variables.scss` | ▣ Motion |
| Steps (a short how-to in order: numbers and two-line names across from md, a rail of diamonds below; sub-steps with duotone icons; one step alone heads a row of sub-steps) | `includes/steps.html.twig`, `_steps.scss`; `_palefire_steps_items()` reads `<li><strong>Name</strong> note</li>`, sub-steps a nested `<ol>`, `[icon:name]` | ▣ Steps; Reserve's How to book |
| Buttons (primary, secondary, arrow) and text links | `_buttons.scss` (`%pf-button-primary`, `%pf-button-secondary`, `%pf-button-arrow`; `.pf-button--*` for markup of its own), `.pf-index-rule__link` | ▣ Buttons and links |
| Object label (a museum tombstone: maker and dates, title, date, medium, size; a hairline, credit line and call number with View record ↗; compact for cards) | `includes/object-label.html.twig`, `_object-label.scss` | ▣ Object labels; on the stage's lead picture |
| Section tabs (labelled tabs over panels: the one relief control, an inset track, a sliding key, solid icons carved when off and Light outlines printed on the key when on, indicator lights) | `paragraph--section-tabs.html.twig`, `_section-tabs.scss`, `js/section-tabs.js`; relief colours `pf-relief()` | – (/v2/special) |
| Exhibition gallery (story) | `paragraph--exhibit-page` | ✔ every gallery |
| Exhibition title page | `includes/exhibit-cover` | ✔ the page's own |
| Text (Plain, Callout, Sources) | `paragraph--guide-text` | ✔ |
| Catalogue record | `paragraph--exhibit-citation` | ✔ |
| Entry with biography | `paragraph--exhibit-entry` | ✔ |
| Figure | `paragraph--exhibit-figure` | ✔ with `image=` |
| Image group / readings | `paragraph--exhibit-gallery`, `--exhibit-reading` | ✔ readings |
| Accordion | `paragraph--guide-accordion` | ✔ |
| Wall display, compare slider | `paragraph--exhibit-page` (wall) | – needs images |
| Audio clip, tape deck, reel | `paragraph--exhibit-audio` | – needs a file |
| Video | `paragraph--guide-video` | – |
| Resource list, librarian card | `paragraph--resource-list`, `--librarian-card` | – guide only |
| Tile showcase, covers shelf | `paragraph--tile-showcase` | – landing only |
| Landing hero (split cover from 44rem: type in two-thirds, the film in the last third; under 44rem the film in a 4:5 box, the lead Highlight rising over it on scroll and the rest as ruled rows; the title as a window on first load, opening on the poster still; on scroll the stage: the film wiped and, on dark reading colours, the title's letters taking the lead Highlight's picture, the Highlights as one full-height picture in its third with their words on a wash, the lead large, a row in hand changing the picture and the large words; otherwise a compact strip; a Contents button beside Log in or register), stacks, department | `paragraph--landing-*`, `includes/landing.html.twig`, `_landing-features.scss` (Split cover), `_entrance.scss` (`pf-split-open`), `js/hero-title-window.js` | – landing only |
| Events carousel, Visit strip | `paragraph--events-carousel`, `_visit.scss` | – |
| Building map, parish map, family trees | `paragraph--building-map`, `--parish-map`, `--family-trees` | – |
| Location page: plate (title + map), ledger, map chip; its parts as sections (room photos, hours and directions, house rules, booking), in index columns with a sticky head from lg; Need to know (up to three required items, in words) | `paragraph--location-space`, `-location-photos`, `-location-visit`, `-location-rules`, `-location-booking`, `includes/space-icon`, `_location.scss`, `js/location-plate.js` | – location pages only |
| Ask Special Collections | `includes/ask-special` | – |
| Exhibitions showcase | `includes/exhibit-showcase` | – its own page (`/v2/special/exhibitions`): two bands with index rules 01 and 02, the breadcrumb in the landing's glass bar, the rail TOC, alternating band colours |
| Header, utility drawers, TOC, footer | `layout/*`, `_utility-panels.scss`, `_toc*.scss` | ✔ around the page |
| Breadcrumb bar (glass sub-header, sticky under the header) | `layout/page.html.twig`, `.pf-landing__bar` / `.pf-page-bar` in `_landing-features.scss` | ✔ the exhibition's own; every page but guides |
| Mobile dock: hours, action, Contents ring; hidden-state tab | `includes/dock.html.twig`, `_dock.scss`, `js/dock.js` | – Special Collections pages, below 768px |
| 404 page: display case, wall text, quips | `includes/not-found`, `_not-found.scss`, `js/not-found.js` | – its own page (`/v2/404`); editors keep the quips in that page's Intro, one a line |

## Changelog

- **2026-11-05:** Title's picture: `pf-title-dip-*`, `--pf-title-dip` and `--pf-title-shown` are removed. `pf-title-feature` paints the floor, a `-webkit-cross-fade()` layer per row, then the lead's picture. A row in hand sets its registered `--pf-title-show-N` to 100%, .5s ease-out after .12s, the column's timing.
- **2026-11-05:** Title's picture: `--pf-title-floor` and the `lighten` blend are back, and `--pf-title-outline` and its drop shadows are removed: the full colour was too distracting. Same floor values as before, 97 to 122.
- **2026-11-05:** Title's picture: `--pf-title-outline` (the same per-theme greys, 97 to 122, the 3:1 line on each page) replaces `--pf-title-floor` and the `lighten` blend. `pf-title-trade` fades in four chained 2px `drop-shadow()`s; `-webkit-text-stroke` draws the variable font's overlapping contours. `pf-title-feature` keeps the grey as `background-color` under the picture, so letters stay painted, and outlined, if it fails to load.
- **2026-11-05:** Title's picture: `--pf-title-floor` (a grey per reading colour, 97 to 122, the 3:1 line on its page) replaces `--pf-title-veil` and `--pf-title-veil-on`. `pf-title-feature` paints the dip's gold, the floor and the picture, with `background-blend-mode: normal, lighten, normal`. `pf-title-trade` now only fades the fill.
- **2026-11-05:** Guide blocks (`guide-blocks.scss`, own library): `.pf-flow` (an `<ol>` on a container query, branches beside their step from 34rem, under it below; lines and arrows in the muted text colour, for 3:1), `.pf-status` (mark and words; Needs attention is the text colour as a plate), `.pf-stats` (a `<dl>`, value drawn above its label), `.pf-code` (focusable `<pre>`). Tokens only. New gallery on the style guide page: re-run its script.
- **2026-11-05:** Title's picture follows the row in hand (`$pf-title-trade-on`, stage only). `--pf-title-pic-2` to `-5` on the `h1`, by place in the list, from each item's `still`, including the blog post. `.pf-landing__stage:has(row k)` sets `--pf-title-shown` (discrete transition, .32s delay) and `pf-title-dip-k` on `.pf-landing__top` (.5s after .12s, one set of keyframes per name so a change restarts them); `--pf-title-dip` is inherited, and the veil layer is `max(--pf-title-veil, --pf-title-dip)`.
- **2026-11-04:** Hours and directions is a numbered section on the band (`--pf-band`) after a cover's map; the sections after it take the band on odd positions. `_palefire_hero_index()` numbers each rule once (no 02.1, 02.2). `.pf-landing__contents` and `_palefire_hero_contents()` removed: `.pf-breadcrumb__section--first` ("Contents" in the crumb) now shows on a cover's first screen at every width.
- **2026-11-04:** `.pf-landing__contents` and `_palefire_hero_contents()` removed: `.pf-breadcrumb__section--first` ("Contents" in the crumb) now shows on a cover's first screen at every width.
- **2026-11-04:** Rail: `--pf-rail-track` (`$pf-hairline`, 1px) and `--pf-rail-mark` (`--pf-g-highlight`, 2px on the current segment); the halo and 5px hover are gone. `.pf-landing__contents`: the first screen's index under the search (`_palefire_hero_contents()`), from 44rem of landing and 52.5rem of window, two columns then three from 64rem. `.pf-breadcrumb__section--first`: "Contents" in the crumb where the index isn't. Panel rows: `.pf-toc__row-num`, number and name read from the section's index rule. TOC ids: `#start` for the Quick Start.
- **2026-11-04:** Index rule numbers on the hero: `_palefire_hero_index()`. Rooms and hours are one section, 02.1 and 02.2 (either alone is 02); sections after the hero count on from whole sections.
- **2026-11-04:** Cover column: 1824px and `--pf-header-gutter` now apply to `.pf-landing__run` only (the hero's section stays that wide from md so its `cqi` sizes hold); the Quick Start, map and sections below keep `--pf-col-max`'s default 1280px and `--pf-section-pad-inline`. `.pf-map__legend--hero`: under `.pf-map__summary`, mono labels, one row per hatch plus the elevator.
- **2026-11-04:** Hero title: `width: min-content` on `.pf-landing__title` in the split cover, at every width: two lines for "Special Collections", and `background-position` centres on the words. A longer title would take a line a word.
- **2026-11-04:** Hero below desktop. Split cover, stage, `pf-split-open` and the title's window start at 44rem of `pf-cover` (was 64rem). Under 44rem, with motion and view timelines: `--pf-film-h` sizes the film's box, the lead lies on it and rises with `pf-film-rise` on `--pf-banner-own` (contain 5% to 70%), its title `pf-split-appear` (contain 50% to 80%); the rest are ruled rows with `.pf-hero-banner__media` off. `$pf-title-trade-on` (`_variables.scss`) limits the title's trade to dark reading colours. `pf-title-feature` sets `--pf-title-feature` (`_palefire_hero_banner_still()`, the portrait cover's md) from 1px of scroll. `--pf-title-veil-on` is now the 3:1 floor on a black pixel per colour (51 to 70%). `--pf-title-trade-fill` keeps the letters solid when `.is-event` holds the lead. `pf-cover-top-wash()` takes `$top` and `$edge-mix`: 80 and 78 under 64rem, where the header's words lie over the column. `PALEFIRE_HERO_BANNER_SIZES`: 90vw from 44rem, 100vw under.
- **2026-11-04:** Landing hero sections: `_palefire_hero_rules()` keys are `guide`, `rooms`, `hours`. The key on the hero (`map.key_indexed`, `.pf-map__key--indexed`) is a plain block under the drawing, no toggle, no rule of its own. `.pf-landing__directions` (`#hours`) holds rule 03 and `.pf-visit--indexed`, a `<section>` with an h2 in the section heading style. Retired: `.pf-visit--keyed`, `.pf-visit__key`, `map.visit`. A section the hero draws itself takes no number.
- **2026-11-04:** Stage hold: `position: sticky` on `.pf-landing__stage` inside `.pf-landing__run` (`display: contents` off the stage), replacing `pf-stage-hold`. The sweep, wash and rows still run on the root scroll timeline.
- **2026-11-04:** Object label on the stage: no panel; a caption at the column's foot (`.pf-hero-banner__plate`, the label's lines run in at 12px, muted, two lines at most, hidden under 45rem tall). The lead's words sit at the foot of their row.
- **2026-11-04:** Object label's first placement: the stage's lead picture (`.pf-hero-banner__plate`, a solid panel of `--pf-hl-wash`, stage only), from `field_exhibit_cover_maker`, `_title`, `_date` and `_medium` on Modular page. Stage row labels sit behind their titles at `--pf-hl-label-strength`, per reading colour, measured at 4.5:1 on the current picture; solid with more contrast.
- **2026-11-05:** `.pf-tiles__shelf-button`: border `var(--pf-section-border, currentColor)`, not `$pf-hairline`.
- **2026-11-05:** `pf-button-wipe`: the fill scales in over `$pf-hover-in` as before and fades out over `$pf-hover-out`, with its scale resetting after the fade. No movement on leaving.
- **2026-11-05:** Hero: hover (`@media (hover: hover)`) or focus on `[data-pf-landing-contents]` gives `.pf-breadcrumb__toc` its hover state (`pf-crumb-lit`, `:has()`), one way, off while `.pf-toc.is-open`. `.pf-breadcrumb__section-label` has no transition with reduced motion or `.pf-a11y-motion`.
- **2026-11-05:** Rail panel: `.pf-toc.is-anchored` (`placeAnchor()`, `js/modular-toc.js`) sets `--pf-anchor-top`, `--pf-anchor-start`, `--pf-anchor-height` and `--pf-anchor-from`, so the hero's Contents button opens it 8px from itself: under it if it fits, else over it, else on the roomier side with the list scrolling. It follows the button on scroll and closes once the button is gone. The crumb and the rail's own hover and focus keep its usual place.
- **2026-11-05:** Hero: `.pf-landing__account-button--contents` beside Log in or register (`data-pf-landing-contents`, `js/modular-toc.js`), a `$pf-hairline` edge and no fill, the list icon after the word. `openContents()` keeps its opener: Esc in the rail or drawer returns focus there, as the dock already did. Under 22rem the account buttons tighten to share a row.
- **2026-11-05:** Ask: `.pf-inquiry__actions` spans the form grid again (`grid-column: 1 / -1`), so `.pf-inquiry__submit` is the form's full width.
- **2026-11-05:** Visit panel buttons (`.pf-landing--cover .pf-visit__link`): `width: 100%` at every width. From `@container pf-cover (64rem)`, `.pf-visit__go > .pf-visit__link` is `flex: 1 1 14rem`.
- **2026-11-05:** Ask: `.pf-inquiry__button` and `__submit` are `width: 100%`. `.pf-inquiry__links > li` has no `max-width`. `.pf-inquiry__actions` no longer spans the form grid, so it sits in the first column.
- **2026-11-05:** Ask: `.pf-inquiry__submit` extends `%pf-button-primary`; `.pf-inquiry__button` stays secondary.
- **2026-11-05:** Buttons: both tiers are Roboto 15px at `$pf-button-weight`, sentence case (`pf-button-type`). Secondary is a plate, `--pf-section-fg` at 9%, with a transparent 1px border (`$pf-hairline` at full strength with `.pf-a11y-contrast`). Hover and focus (`pf-button-on`: hover only with `@media (hover: hover)`): `pf-button-wipe`, a `::before` of `--bs-emphasis-color` scaling in from the start and out past the end on `$pf-hover-in` and `$pf-hover-out`, words to `--bs-body-bg`; instant with reduced motion or `.pf-a11y-motion`, gone with forced colors. `.pf-quick-guide__link`, the cover's `.pf-visit__link`, `.pf-inquiry__button`/`__submit`, `.pf-landing__account-button` and `.pf-paths__cta` now extend `%pf-button-secondary`; `.pf-landing__submit` and `.pf-footer__cta` include the type and wipe. `.pf-landing__account-button--primary` has no rules left. `.pf-exhibit-button` is unchanged.
- **2026-11-04:** Stage title: `--pf-title-veil-on` per reading colour, measured on the pixels under the letters at 1100, 1440 and 1920: Twilight and Night 51%, Cream, Soft and Lavender 55%, Material 56%, Forest 57%, Midnight 58%, Dusk 61%, Cocoa 62%, Sepia 66%, Sage and Solarized 70%. Tuned to the current poster.
- **2026-11-04:** Hero Highlights on the stage: the wash is one gradient, `.pf-hero-banners::after`, spanning the list's rows on the `ul`'s grid (`minmax(0, 1fr) repeat(5, auto)`) and 10rem above: transparent, 78% at 10rem, 88% at 40%, the page colour at the foot. It fades in over 28% to 38% of the run (`pf-hl-wash`). Hairlines are `.pf-hero-banner__link::before`, drawn out with `pf-hl-draw`; words rise 36% to 76%, 8% apart. Replaces `--pf-hl-mix`.
- **2026-11-03:** Section tabs: choosing a tab no longer writes its id to the address, and clears one already there (`select()`, `replaceState`, no history entry). `#ask` and `#ask-question` links still open their panel.
- **2026-11-03:** Stage title: `pf-title-trade` (scroll timeline, first 30% of the run, the film's sweep) takes `.pf-landing__title--still` from gold to the still. `@property --pf-title-veil` rises from 22% to `--pf-title-veil-on`: 51% Twilight and Night, 55% Cream, Soft and Lavender, 70% otherwise, for 3:1 on the still's extreme pixels. With `html.pf-fade` the trade joins the title's list in `_entrance.scss`. Solid gold with `.pf-a11y-motion`, `.pf-a11y-contrast`, more contrast or forced colours.
- **2026-11-03:** CSS rule: never end a `:has()` selector in `*` or `:not()` (`.a:has(.b) > *`); it restyles the whole page whenever the page changes, on every page. Set a variable from the `:has()` on the parent and read it from `:where(.a) > *` with a fallback (`--pf-guide-measure`, `--pf-gallery-measure`, `--pf-person-details-col`, `--pf-day-area`, `--pf-day-min`).
- **2026-11-03:** Ask block: `--pf-inquiry-question` holds the FAQ question's size, shared by `.pf-inquiry__accordion .pf-accordion__summary` and `.pf-inquiry__intro`. The intro takes the first question's top rule (`--pf-guide-rule`), padding (.85rem .25rem), weight 500, line height 1.3 and heading color.
- **2026-11-03:** Hero Highlights on the stage (`.pf-hero-banners`, `$pf-split-moving`): the list fills the film's box. Pictures are stacked absolutely in the `ul` (`li` and link static); `--pf-hl-wash` is the page colour, hairlines between rows. `.pf-hero-banner__others` in the first item repeats the others' words, `aria-hidden`, shown only on the stage. Swaps are `:has()`, no script. Order is now banners, then the blog post, in every layout. `PALEFIRE_HERO_BANNER_SIZES` and `palefire_preprocess_responsive_image()` give the hero its own `sizes`.
- **2026-11-03:** Section tabs: `[data-pf-tabs]` animates its height between panels (`GLIDE_MS` 450, the entrance's easing, `overflow-y: clip` while it runs, released at the end; `js/section-tabs.js`). Not for links, Find or reduced motion. `placeMargin()` sets each panel's `scroll-margin-top` to the tab bar's height plus the sticky strip and 1rem.
- **2026-11-03:** Split cover: `--pf-split-title` holds the title's size; `.pf-landing__intro` is capped at 4.68 times it, the width of "Collections" (was 44ch).
- **2026-11-03:** Buttons: `$pf-button-weight` (500; Consolas and Monaco have no 500, so regular) on both tiers and the buttons with their own rules. Secondary edges are `$pf-hairline`, gold on hover and focus as before: `%pf-button-secondary`, `.pf-quick-guide__link`, the cover's `.pf-visit__link`, `.pf-inquiry__button`/`__submit`, `.pf-landing__account-button`, `.pf-exhibit-button`. Fields and wordless buttons keep 3:1.
- **2026-11-03:** Footer curtain: `.pf-swup` shadow `0 2rem 3rem -1.5rem rgb(0 0 0 / .35)` (nothing above its top edge). Section tabs: `--pf-tabs-light-glow`, `--pf-tabs-light-bloom`, `--pf-tabs-light-edge` on the lit `.pf-tabs__dot`; light styles (`html:not(.pf-reading-dark)`) set `--pf-tabs-light-on: #fff7e8`, glow `rgb(255 172 64 / .9)`, bloom `rgb(255 190 110 / .5)`, edge `rgb(0 0 0 / .18)`.
- **2026-11-03:** Cover (`$pf-split`): `.pf-landing__hours-link` extends `%pf-landing-label`; `.pf-landing__hours-label::after` is a silent `\00b7` once `.pf-landing__hours-today` is filled.
- **2026-11-03:** `.pf-landing__account-title` removed (template and styles); `.pf-landing__account` is a `div`, no `aria-labelledby`.
- **2026-11-03:** `.pf-landing__account-label` removed (template and styles); `.pf-landing__account-title` has no top margin. `.pf-landing__account-note a::after`: `\2192` (`\2190` in RTL), silent, .2em nudge on hover/focus, still under reduced motion.
- **2026-11-03:** Ask us tab from 80rem of `pf-cover`: `.pf-quick-guide__panel:has(> .pf-tabs__content > .pf-inquiry)` sets the content and `.pf-inquiry` to `display: contents`; `.pf-inquiry__faqs` columns 1–2 row 4, Before you ask row 5, `.pf-inquiry__panel` columns 3–5 from row 4 (level with the FAQs, same top margin), sticky at `--pf-header-h` + `--pf-landing-bar-h` + 1.5rem on windows ≥ 52rem tall.
- **2026-11-03:** Cover column: `--pf-col-max` (default 1280px) replaces the hard 1280px caps on the landing (`--pf-hero-inset`, `--pf-cover-gutter`, `.pf-landing__onview`, the lead panel, `.pf-visit`, `.pf-landing__visit`, the map stage's spill, the sections' `--pf-hero-col-pad`). On the cover (`.pf-sections:has(.pf-landing--cover)`) it is 1824px and the gutter is `--pf-header-gutter`, so content starts on the logo's edge. Stage: `pf-split-wipe` over 0–30% of the run, ease-out; banner *n* over 32+10(n−1)% to 40+10(n−1)%, ease-out. `pf-bar-stuck` waits for the end of the run (stage probe); `pf-hero-past` (probe at run + 100svh) shows the TOC rail (lg) and toggle. `pf-split-open` 1.4s ease-in-out after 0.6s; `pf-title-fill` 1.2s after 2s.
- **2026-11-03:** `--pf-header-h` falls back to `$pf-header-bar-compact-h` when the header is compact for good. `pf-split-open` is an opacity fade (1.6s ease-in-out after 2.6s); `pf-title-fill` 1.2s after 4.2s, and the title window's canvas holds until the column's fade ends. Film bar (`.pf-landing__film-bar` in the cover's media): `.pf-landing__film-caption` (mono label, ellipsis) and the video button as a word (`.pf-landing__bg-toggle-word`, Pause/Play by `data-state`; the full label visually hidden), white over a shade from 70% black at the foot; the cover prints no `.pf-landing__dock`; a focused button shows the film (`opacity`/`clip-path` !important). The compact strip (16:10, captions under) is from 44rem only.
- **2026-11-03:** The hero’s other tabs keep the Quick Start’s first column (`asides` in `includes/section-tabs.html.twig`): `.pf-quick-guide__title`, `__intro`, `__visit` on `.pf-quick-guide__panel`, the tab’s blocks in `.pf-tabs__content` (columns 2–4 from 64rem of `pf-cover`, 3–5 from 80rem; container `pf-tab-content`, where `.pf-inquiry` is one column under 48rem). Link buttons: `pf.quick_links(links, hours)`.
- **2026-11-03:** The stage (`--pf-stage-run: 100svh`, scroll(root) timelines; 64rem of `pf-cover`, min-height 40rem, motion): `pf-stage-hold` translates the media, top, form and banners by the scroll for the run; `pf-split-wipe` clips the film from the left over 0–45% of it; the banners are an absolute column in the film's third (rows 1–2, flex column to the form's foot), each a row (picture 4:3 at `min(clamp(5rem, 12svh, 8rem), 36%)`, label, title in two lines), rising in (`pf-split-appear`) from 35% to 85%; `:focus-within` shows one at once; the Quick Start takes the run as margin. Title still: `.pf-landing__title--still` with `--pf-title-still` (the hero's Background poster) under `background-clip: text` and a 22% currentColor veil, `pf-title-fill` (`-webkit-text-fill-color` from transparent) as the column opens; none with more contrast or forced colours. Researcher card: one button, Log in or register.
- **2026-11-03:** Split cover (`$pf-split`, `--pf-split-film: 33.333%`): from 64rem of `pf-cover` the lead's media is the right third, absolute from the page's top to the form's foot, over a 100svh spacer; type and form keep `--pf-split-film` + two gutters clear; title `clamp(4rem, 9.2cqi, 9rem)`, -.03em, .92; no text shadows, kicker in `--pf-toc-color`, hours badge transparent, field on `--pf-g-surface` with a hairline; the researcher card is a plain row. Under 64rem the media is row 3, 4:5, `max-height: 60svh`, stretched across the gutters. `pf-split-open` (clip-path from the foot, 1.2s after 2.6s) opens the column; `.pf-landing__title-window` is the canvas that plays the clip in the letters until then. Strip (`$pf-strip`): banners 16:10, colour at rest, caption under the picture, one row from 44rem, `--pf-banner-rise: 0px`.
- **2026-11-03:** Quick Start band painted by `.pf-quick-guide::before` (clipped on its own, so content may cross its edge). With Section tabs the bar sits `--pf-hero-gap` under the banners' labels, centred from md, its track's centre line on the band's top edge (`--pf-quick-band-top` from `--pf-tabs-label-h` + `--pf-tabs-rail-h` / 2); each panel opens with its own rule 01.
- **2026-11-03:** Section tabs head the hero's Quick Start (`.pf-quick-guide--tabs`, `includes/section-tabs.html.twig`): the tab bar under rule 01, above the heading, and each panel the whole section; the Research panel (`.pf-quick-guide__panel`) carries the Quick Start's grid. Before you visit follows the paths in the source, so it comes after them on phones. Ask us uses the utility menu's icon (`duotone/comments-alt`).
- **2026-11-03:** Section tabs icons inverted: off, the tab's own icon (solid or duotone) carved into the track; on, the Light outline of the same name printed on the key in ink (`.has-line`, `.pf-tabs__line`; was `.has-idle`, `.pf-tabs__idle`). High contrast: inactive icons in `--pf-tabs-idle`, no carving. Study's icon: `book-open`.
- **2026-11-03:** Section tabs icons: 1.25rem below md, 1.75rem from md. Inactive Light outlines carved: `drop-shadow(-1px -1px .5px carve-wall) drop-shadow(1px 1px .5px carve-lip)`, color carve-wall at 65%.
- **2026-11-03:** Section tabs key: `--pf-tabs-bevel` (inset 1px 1px 0, the light at 70%) replaces the outward light in `--pf-tabs-raise` and `--pf-tabs-lift`. `--pf-quick-tabs-gap` on short windows: 5rem.
- **2026-11-03:** Relief tokens: `--pf-g-relief-band-*` (shade, light, carve, carve-hover, carve-wall, carve-lip) from each swatch's surface color (`pf-relief-from()`). The Quick Start's tab bar, set into the band's edge, uses them and the band color for its track and key.
- **2026-11-03:** Section tabs: inactive outlines `--pf-tabs-carve-wall` at 65%, .5px lip. A tab body keeps the 52rem measure unless it holds the Ask block (`.pf-tabs__body.pf-guide:has(.pf-inquiry)`: full column).
- **2026-11-03:** Section tabs: inactive Light outlines in `--pf-tabs-carve-wall` with a 1px `--pf-tabs-carve-lip` drop shadow (was `--pf-tabs-carve`, .75px).
- **2026-11-03:** Section tabs relief back to the mockup's: `--pf-tabs-well` inset 4px 4px 9px / -4px -4px 9px, `--pf-tabs-raise` 3px 3px 6px / -2px -2px 5px, `--pf-tabs-lift` 6px 6px 14px / -3px -3px 8px.
- **2026-11-03:** Section tabs: inactive Light outlines are engraved (`--pf-tabs-carve` with a .75px `--pf-tabs-carve-lip` drop shadow; `--pf-tabs-idle` under high contrast), and icons don't change on hover; only the label does.
- **2026-11-03:** Section tabs: an inactive tab's icon is the Light outline of the same name in `--pf-tabs-idle` (hover: `--pf-tabs-hover`); the active one is the tab's own icon on the key, crossfading (.3s, after .12s). Carving only where no Light file exists, blurred .5px. Icons 1.75rem at every width. Key 4.25rem, 4.5rem from md, 4.25rem again on windows under 50rem tall; relief shadows short and wide (`--pf-tabs-raise` 2px 3px 8px, `--pf-tabs-well` inset 2px 3px 10px).
- **2026-11-03:** Index rule numbers in Section tabs: a tab's rule is the section's number plus its place (01.1, 01.2, 01.3); letters stay for alternatives (the Research paths' A, B, C). Header glass: `view-transition-name` is set on `.pf-header` only during a native visit (`html.pf-vt`); a named element is a backdrop root and its frosted `::before` would blur nothing.
- **2026-11-03:** Object label (`includes/object-label.html.twig`, `.pf-object-label`, `--compact`): maker in mono capitals at 12px .14em with tabular dates, the title at 18px 500 in the heading colour (`<cite>` italics for a published work), date, medium and size in the text colour, then a hairline and the record: credit line (muted, 14px) and Call no. (mono capitals; the number in the heading colour, tabular, `nowrap`) with View record ↗ (the index rule's text link). Compact for cards: maker, title, date, call number. New plate 10, Object labels (`PALEFIRE_STYLEGUIDE_PLATES`, `.pf-sg-labels`); "Plates: 10". Section tabs and their relief tokens (`--pf-g-relief-*`) added to the inventory.
- **2026-11-02:** Location: `.pf-location__actions` is a wrapping flex row, the button then `.pf-location__contact` ("Questions? Name, title", mail link, `·` phone; muted text, links in the text colour, underlined; 14rem to 28rem wide). `.pf-location__photo-note` is a second caption line in the muted tone (seats). Need to know items carry their policy's state mark; on a walk-in room the list is titled House rules. Facts: Seats on walk-in rooms, room details after Notice, six columns from 52rem when there are six.
- **2026-11-02:** Index rule (`index_rule` macro): the label is optional; location parts print the number alone. Location facts (`.pf-location__facts`): Access, Capacity, Sound, Price, Notice (three to five; the six-column rule is gone). Photo placeholder `.pf-location__frame` (4:5, 4:3 when it leads below md): the surface fill, a hairline edge, the `camera` space mark at its middle; the photo grid's gap falls back to 2rem (the gutter token was unset outside the top section, so photos touched), caption "Photo coming soon". Landing kicker (`.pf-landing__kicker`) is a `<p>` over the `<h1>`, row 1 of the cover's grid from 40rem; it enters with the title. Building map key, indexed: hidden `<h2>` Rooms and access, group titles `<h3>`. Book titles (`.pf-book__title`) are spans.
- **2026-11-02:** Cover swatch tint (`.pf-landing--cover .pf-landing__lead > .pf-landing__media::before`, `_landing-features.scss`): `--pf-cover-bg` at 40% in `mix-blend-mode: color` over the video and its washes. Off with more contrast (`prefers-contrast: more`, `html.pf-a11y-contrast`) and forced colours. New colour rule for photographs (above).
- **2026-11-02:** Motion: no page fade (`base/_fade.scss` no longer holds the body at 0.01 until `.pf-ready`; `.pf-ready` is gone). The entrance (`_entrance.scss`) and the hero banners' rise key on `html.pf-fade` and run from the first paint; `pf-enter-title` rises 1rem at full opacity. New token `$pf-reveal-from` (.85): the waiting state of `.pf-reveal` (headings, now -.75rem), `.pf-paths--reveal` stops, `.pf-tiles--reveal` cards (16px, stagger ±24px) and the events carousel's parts. Location map chip (`.pf-location__plate`) hides once the footer reaches the screen's lowest eighth.
- **2026-11-02:** Buttons: pressed state, mixin `pf-button-pressed` (`_buttons.scss`): `:active` lays an inset `box-shadow` of `currentColor` at 14% over the button, on at once, fading out over `$pf-hover-out`; `-webkit-tap-highlight-color: transparent`. In `%pf-button` and in the buttons with their own rules (`.pf-quick-guide__link`, the cover's `.pf-visit__link`, `.pf-inquiry__button`/`__submit`), which add `box-shadow` to their transition.
- **2026-11-02:** Visit panel buttons (`.pf-landing--cover .pf-visit__link`): full column width below `@container pf-cover (64rem)`, stacked, .75rem from the rule or map above, .5rem apart; phones put 2rem between hours and map. From 64rem they keep `min(100%, 17.5rem)`.
- **2026-11-02:** Location top: the policy marks and their tooltips (`.pf-space-pin*`, `js/location-pins.js`, library `palefire/location-pins`) are replaced by Need to know (`.pf-location__need*`): a mono label in the accent over ruled rows, each a mark in the accent, a label and a muted detail. Up to three: the room's Required policies less those its access says (`PALEFIRE_ACCESS_POLICY_KEYS`), and Fees apply for an Event rental (`_palefire_location_need_to_know()`). Stacked at every width.
- **2026-11-02:** Dock motion calmed: `--pf-dock-ease` `cubic-bezier(.33, 0, .15, 1)` over `--pf-dock-dur` .5s. The action pill opens, then its words fade in (folding is the reverse); panel contents fade with the panel's height.
- **2026-11-02:** Policy mark tooltips: Required in `--pf-g-accent` (5.7:1 or better on `--pf-g-surface` in every reading colour); Allowed and Not allowed stay muted.
- **2026-11-02:** Access kind `rental` is labelled Event rental (was Room rental), in the ledger, the map key and tooltips; the field option reads "Event rental (fee, approved by staff)".
- **2026-11-02:** `.pf-landing__kicker-place` never wraps inside ("Floor 1" drops to the next line whole, rule first). Room rental's default button words: "Request a date" (was Reserve), also the dock's.
- **2026-11-02:** Location pages: `.pf-location__entrance`, the accessible entrance under Hours and directions (beside the hours from lg, under the links): the step-free mark, a mono label, the words, on a hairline. From `field_space_entrance` on the room or its building (`_palefire_space_entrance()`). The Step-free entrance policy mark is retired.
- **2026-11-02:** Steps: numbers again (1, 2, 3; Roman read as confusing). Sub-steps (`.pf-steps__stops`): a nested `<ol>`, each a small diamond with a Font Awesome Pro duotone icon (`[icon:name]`, inlined from `libraries/fontawesome-pro`) or its number, on a rail of its own. One step alone (`.pf-steps--single`) has no number and its sub-steps run across from md. Plate 09 shows both.
- **2026-11-02:** Steps (`pf-steps`, `includes/steps.html.twig`, `_steps.scss`): the Quick Start's anatomy with Roman numerals (I, II, III). From md, across: a large accent numeral beside a two-line name (broken at the middle word), the note under both; below md, one rail with each numeral in a diamond. Reserve's How to book uses it when each step opens with a bold name. New plate 09, Steps; numbering rule added.
- **2026-11-02:** Default button words by access (`_palefire_space_access_kinds()` `cta`): Plan your visit / Book a time / Reserve, kept under 24 characters for the dock.
- **2026-11-02:** Map: `.pf-map__knock`, a black rect in the here room's hatch mask sized by `building-map.js` from the name's glyphs (cap height over each baseline, padding .7em), with the name's transform, lift and reveal; hidden below 576px. `hereGap` 36 → 68. The lit here name is `--pf-map-hover` (was the ink); on Solarized mixed 25% white.
- **2026-11-02:** `pf-location-backdrop-whole`: bottom 0 with `max-height: 100lvh` (was `height: 100lvh`), so the backdrop never passes the Location section's foot.
- **2026-11-02:** Map: `[aria-current]` room back to the hatch at full strength, its name in the ink (`.is-here.is-active`); the solid `--pf-map-hover` fill and dark name are undone.
- **2026-11-02:** Location ledger: `.pf-location__facts::before` has no fill (hairline, shadow, blur over a backdrop); six facts get six columns from 52rem. Backdrop: a swatch-colour `color` layer over the veil; `--pf-location-muted` lifted 40% toward `--pf-g-heading` over it. New `includes/about.html.twig` ("~100", read "about 100") and space mark `receipt`. Map: the `[aria-current]` room is filled with `--pf-map-hover`, its name in `--pf-map-on-hover`, focus ring in the ink; the key groups Walk in / Book online / Room rental (`s.hatch`, `s.access_label`); indexed key two columns from md, one per group from 1200px.
- **2026-11-02:** Location top: the map's empty head and aside rows lose their row gap, so the drawing starts level with the eyebrow (it sat 2.7rem lower). The ledger sits on a panel (`.pf-location__facts::before`): the section color at 80%, hairline frame, the hero search field's shadow (`0 1.5rem 3rem -1rem`, .18, .5 over a backdrop, where it is also frosted); 1.5rem past the text from 52rem, edge to edge below. More contrast and forced colors keep the hairline only.
- **2026-11-02:** Buttons: one system in `_buttons.scss`. Primary is the swatch accent filled (`--pf-g-accent`, ink `--pf-g-on-accent`), hover to the text color; secondary is unfilled with the control border (`--pf-section-border`, else `--pf-g-scroll`); both 13px .12em mono capitals, 48px (dense rows 44px). Arrow: → on the site, ↗ for `target="_blank"`, stepping the way it points. The location button, the landing's Register and Quick guide buttons, the Ask buttons and the footer button follow it; index-rule links show ↗ when they open a new tab; text-link underlines sit at .35em. New plate 08, Buttons and links.
- **2026-11-02:** Location pages: the eyebrow is the landing's kicker (building, rule, floor); the lede is `--pf-hero-text`; the head sits at the top of its column. Google Maps is linked once (the index rule). The facts run in one row of equal columns from 40rem. Reserve splits its text at the subheads: intro, the steps (across from md), the button, then the other parts on 14rem columns; no index-rule link. Room photos below md: the first across at 4:3, the rest in pairs.
- **2026-11-02:** Exhibition walls below md: a tile alone on its row fills it (cap 60svh tall, not 1.75 times the row), and the last row fills too.
- **2026-11-02:** Bare exhibition walls below lg: the foot wash is sized to the text (solid behind it, eased out over `$pf-bare-fade`, 8rem), not a share of the screen.
- **2026-11-02:** Phones: with the section crumb showing, the breadcrumb drops the crumbs between Home and the page, and the section keeps its name (up to 55% of the row). Bare exhibition walls below lg get a lighter foot wash (`$pf-bare-wash`, 36% to 76%).
- **2026-11-02:** Location pages: today's hours sit right under the title, as on the landing hero (title, hours, line, marks, button), with the landing's own spacing (`.pf-landing__hours`; the location override is gone).
- **2026-11-02:** Location pages: the sound level is the last policy mark (`.pf-space-pin--sound`, the facts' three-bar meter; tooltip "Quiet" / "Sound level", no state flag). House rules fill the content column (no auto margins) and their groups sit on `auto-fill` 14rem columns, so two groups take two of three columns at 1440 instead of stacking.
- **2026-11-02:** Collapsed dock glass thins to 66% at the top edge (`$pf-dock-glass-top`); the solid page-color foot is the bottom 25% so muted text keeps 4.5:1.
- **2026-11-02:** The dock's solid foot is the page color (`--pf-dock-foot-rgb`), not the chrome, so it meets Safari 26's toolbar band (tinted from the page background) without a seam; the fade into the 75% chrome glass ends at 90%.
- **2026-11-02:** Dock action pill and Contents ring share a 34px size (pill 34px tall, 12px from the ring; the open ring fills a 34px disc in its 44px target). `.pf-dock-foot`, a 16px solid strip behind the dock's solid foot, lets Safari 26 tint its toolbar band to match.
- **2026-11-02:** Collapsed dock glass is a gradient (`--pf-dock-fill`): solid in the bottom 20%, thinning to 75% (`$pf-dock-glass-top`) by 85% of its height; open panels stay 92%.
- **2026-11-01:** The dock rests on Safari 26's floating toolbar instead of running under it: the tint strip, the bleed and the 76px toolbar reserve are removed (12px under the row everywhere).
- **2026-11-01:** `.pf-dock-tint`, a 12px solid strip at the bottom edge in the dock's color (iOS WebKit only), so Safari 26 paints the band behind its toolbar to match the dock.
- **2026-11-01:** The dock's glass bleeds 160px below its edge (`--pf-dock-bleed`), so it runs under Safari 26's floating toolbar, where `bottom: 0` is the toolbar's top.
- **2026-11-01:** Location parts in Swiss index columns from lg: rule across, `.pf-section__head` (heading + lede, sticky) in 4/12, content in 8/12 (`.pf-section--aside`, `index.aside`). Hours beside its links; Booking text in two columns from xl. Dark backdrop fades into the section color at the Location section's foot (no bottom padding).
- **2026-11-01:** Location pages are one section per part, as /v2/special: Location (the top only), `location_photos` (`.pf-location__photos`), `location_visit` (`.pf-location__visit`), `location_rules`, `location_booking`, numbered by `_palefire_location_index()`. The floors' frost fades in with the map's draw (`.pf-map.is-playing`). The ledger has no rules; over the photo it is frosted like the floors. On portrait screens and iOS the backdrop fills one screen from the top and fades out (`pf-location-backdrop-whole`).
- **2026-11-01:** The dock is set in Roboto (mono only for uppercase labels and section numbers) on the landing bar's chrome frost at 92%, so muted text holds 4.5:1 over anything. The closed dot uses `--pf-g-scroll`. The dot steps in to the second line's 16px indent. The Contents ring shows "5", not "05".
- **2026-11-01:** The dock's week gains Ask Us, an accent pill like the row's action (icon dropped below 360px), beside "See full hours". The utility menu stays in the header on every page; its Hours tile opens the dock's week on dock pages.
- **2026-11-01:** Mobile dock on Special Collections pages, below 768px: the drawers' colors in a thicker glass (90%, `$pf-dock-glass`, so muted text holds 4.5:1 over anything), 28px top corners, a hairline top edge. Status dots differ by shape as well as color (filled open, accent ring soon, muted ring closed). The Contents ring uses the TOC's accent and mono numbers; the current TOC row takes the accent rail. Actions are accent pills (Call desk the quieter rule tone), at least 118px wide, dropping their icon below 360px. Motion: `--pf-dock-ease` (`cubic-bezier(.2, .8, .2, 1)`) over `--pf-dock-dur` (.35s); none with reduced motion.
- **2026-10-31:** No shadow between the header and the breadcrumb bar (the compact header's fell across it). The 404 page's spotlight and dust reach up behind the header and bar, which are thinner glass there until the page scrolls.
- **2026-10-31:** First version.
  - Seven plates: overview, colour, type, grid, spacing, lines, motion.
  - "Exhibition blocks" gallery with editor notes.
  - `$pf-exhibit-ease` and `$pf-exhibit-curtain` moved to `_variables.scss`.
- **2026-10-31:** The breadcrumb bar is on every page but guides. Plain pages and the 404 page print it at the top of `#swup`, Modular pages at the top of `<main>` (`.pf-page-bar`), sticky under the header for the whole page, its crumbs starting where the logo does. Landings, exhibitions, the showcase and location pages keep theirs. The 404 page's mono label is now one of the editors' quips at random.
- **2026-10-31:** Exhibitions showcase follows the landing's page furniture. The breadcrumb sits in the glass sub-header bar (`.pf-showcase-head`, pinned like an exhibition's), the rail TOC lists Browse and All exhibitions, each band opens with an index rule, and the second band and every other section after it take the swatch surface, as on the Special Collections landing. Everything starts on the section padding line, and the covers line up with the summary's top.
- **2026-10-31:** Exhibitions showcase shares the landing's styles: `data-pf-hero` (1280px column, type tokens, section headings), the clear-then-glass header and bar, the 4/5-column grid with `--pf-grid-gutter`, the index-rule rhythm, and a new shared token, `--pf-hero-title-size`.
- **2026-10-31:** The Grid / List switch (`.pf-tiles__view`) is now also a filter: the Exhibitions showcase's All / Digital / Physical buttons reuse it, with a count after each label.
- **2026-10-31:** Page kinds. `data-pf-page` on `<html>`, with `$pf-system` and `$pf-banded` in `_variables.scss`: the 1280px column, section headings and alternating bands now apply to every Modular page but guides, forms and exhibitions. The `--pf-hero-*` type and rhythm tokens moved to `:root`; guide section headings use `--pf-hero-h2-size`. Location pages use the five-column grid, the shared title and h2, and index rules.
- **2026-10-31:** Location pages: the map plate (the building drawing, small, at the foot of the details column once the map has scrolled away; a chip at the bottom of the screen on phones or wherever the column would cut it off). Policy marks lose their built-in square: a required mark shows a corner flag on its frame instead, and the Required group's mark is an asterisk.
- **2026-10-31:** Location pages, layout 01: the title beside the map (2/5 and 3/5, key folded), a full-width ledger of facts with the policy marks, then numbered parts (`.pf-location__part`, heading over two columns, content over three; `--band` on odd parts). The map chip is always the bottom-left chip. New placeholder `%pf-visit-tokens` for the Visit colors; Booking text styles its h3, lists and numbered steps.
- **2026-10-31:** Location pages: the policy marks sit under the title's line; the head is centred on the drawing; the building map shows without its legend (`map.no_key`), the room lit and marked.
- **2026-10-31:** Location pages: "The room" part (`.pf-location__photos`, `.pf-location__photo`): the room's photos three across at 4:5, captioned in the label voice under a hairline. Image style `pf_location_photo`.
- **2026-10-31:** Location pages: `.pf-location__intro.has-backdrop` puts the room's first photo, blurred and faint, behind the top on dark swatches (`--pf-location-backdrop`, painted as an outset border image so it spans the screen without scrolling).
- **2026-10-31:** Location backdrop: the photo darkened rather than blurred, covering and fading out on every side, with a frosted oval under the map (`.pf-location__map::before`, backdrop-filter, radial mask).
- **2026-10-31:** Location backdrop: the photo is fixed to the window; the oval is replaced by `.pf-location__frost`, a backdrop blur masked to the map's floors (`pf-map__floors` + room hit areas, built by `js/location-plate.js`).
- **2026-10-31:** Location backdrop: no edge fades; one even 75% black wash, the least that keeps the top's text at AA over the photo's whitest pixel. It runs on to the first band.
- **2026-10-31:** Location parts follow /v2/special's section pattern: index rule (with its link), `.pf-location__part-title`, `.pf-section__lede`, then the body at the full width; `.pf-location__part-grid` removed. Backdrop: the room term's photo in `pf_location_backdrop`; the frost blurs only.
- **2026-10-31:** Location backdrop: monochrome and low in contrast instead of the black wash: a grey `color`-blend layer plus a 35-grey veil at 87% (tones 30-64 of 255, the same AA ceiling as 75% black).
- **2026-10-31:** Building map: `--pf-map-dim-text` (.6) for the other names and numbers while one room is lit, `--pf-map-dim` (.35) for hatching and dashed lines only; floor names and icons never dim. A page's own room rests lit after the draw-on (`data-map-here`), its name in the ink (`.pf-map__label.is-here`). Location backdrop: a top fall to 22rem; policy marks (`.pf-space-pin__button`) frosted; floor ink #b0b0b0 over it.
