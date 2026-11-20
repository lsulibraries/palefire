# Creole Echoes: migration corrections

Exhibition: *Résonances créoles / Creole Echoes: The Francophone Music and Literature of Nineteenth-Century New Orleans*, Hill Memorial Library, August 20 – November 3, 2001. Curated by Frédérique Spill and Jean-Marc Allard Duplantier, with an introduction by Salwa Nacouzi.
Old site: the Showcase pages under `/creole` (introduction, case1–case20, the three biography pages, the lesson plans, questions and credits), copied from the 2001 web exhibition. Some of their characters were mis-encoded; those are decoded back to the right letters.
New page: `/v2/special/exhibitions/creole`, built by `scripts/palefire_exhibit_creole.php` from `scripts/data/creole.json`. The node is created unpublished.

## Changed on the page

- Seventeen galleries, all walls: Introduction; the twenty cases, grouped where the old site split one subject over several pages (The Doctor, the Priest and the Dandy takes in Mercier, Rouquette and Canonge; Beyond the “Belle Créole” takes in Queyrouze and de la Houssaye; Crossing the Musical Color Line takes in Gottschalk and Barès; Opera in New Orleans takes in both opera cases); For Teachers takes in the letter to educators, the three lesson plans and the Questions for Comprehension.
- Each gallery opens with a text plate (a heading and a summary from the case’s own text), and the long biographies are plates of their own. Plate titles and summaries are new; see below.
- The credits page is the credit line under the title.
- Quotations that the old pages set in italics on their own lines are block quotes.
- “See:” lines are citations under the text they belong to.
- The French version (`/creole/fr/…`) is not migrated; the English text is the whole exhibition.
- *Free People of Color in Louisiana* (`fpoc`) covers some of the same people (Barès, Desdunes, Lanusse, Roudanez); the two exhibitions share no pictures.

## Picture sources

147 pictures. The cover is George François Mugnier’s stereograph No. 374.

- **Rescans from the Louisiana Digital Library (8).** The old site had these at about 300px; the new page uses the LDL masters (2,000–2,560px):
  - Mugnier stereographs, George François Mugnier New Orleans Scenes, Mss. 1089, 1552: No. 374, Old Spanish House, Royal St. (`lsu-sc-gfm:31`, the cover); No. 375, Old House, French Quarters (`lsu-sc-gfm:32`, Introduction’s lead); No. 418, Old Houses, Toulouse & Dauphine (`lsu-sc-gfm:36`, The Creole Family’s lead); No. 420, Old Houses, St. Peters & Chartres (`lsu-sc-gfm:37`). The old site cropped each to one half of the card; the new page shows the whole card.
  - Sidonie de la Houssaye Papers: the 1882 school announcement (`lsu-sc-p120701coll19:3692`, For Teachers’ lead); the 1894 funeral notice (`…:4254`); the postcard of her home (`…:3892`, Beyond the “Belle Créole”’s lead); page 209 of *Octavia la quarteronne* (`…:3824`).
- **From the old site (139).** The 2001 images from `/sites/all/files/sc/exhibitions/e-exhibits/creole/caseN/large/`, about 300px on the long side. There are no other images of them:
  - `R:\SpecialCollections\Exhibitions` holds the exhibition’s text files and press release, but no images.
  - These have no match in the LDL: the sheet music, the Gottschalk and Queyrouze pictures, the French Opera House and Creatore pictures (`act:44` is a 66KB thumbnail), and the Beauregard and Lytle bayou photographs.
  - The de la Houssaye portrait clipping is in the LDL only as the whole newspaper page (`coll19:1330`), so the clipping stays.
  - Page 208 of *Octavia la quarteronne* is not in the LDL’s copy.
- Each rescan’s caption links to its LDL record.

## Corrected

- Typos: “perineal favorite” → “perennial”; “Renne” → “Rennes”; “Pierre Delcour” → “Pierre Dalcour”; “back people” → “black people”; “Frédérique Chopin” → “Frédéric Chopin”; “old Caladonia” → “Caledonia”; “Lake Ponchartrain” → “Pontchartrain”; “a rill or a jig” → “a reel”; “the troop” → “the troupe” (opera); “New Orleans premier” → “premiere”; “April1862” → “April 1862”; “France’s interest lie” → “interests lie”; “Barès’s dedicated” → “Barès dedicated”; “Desdunes book” → “Desdunes’s book”.
- Captions: “Tabacco” → “Tobacco”; “Fascimilie” → “Facsimile”; “qinze” → “quinze”; “Cemetary” → “Cemetery”; “L’Ouverature” → “L’Ouverture”; “Queyrouz” → “Queyrouze”; “Willam” → “William”; “Craegus Aestivalis” → “Crataegus aestivalis”; “McIlheney” → “McIlhenny”; “Titcombe” → “Titcomb”; “pubishing” → “publishing”; “Sheetmusic” → “Sheet Music”; “C. Scribner’s sons” → “Sons”; a call number’s closing bracket.
- Dates: “(New Orleans, 1972)” → “1872”; “Peychaus & Garcia, 1976” → “Peychaud & Garcia, 1876”.
- Titles: “Refléxions” → “Réflexions”; “Résonance Créoles” → “Résonances créoles”; “(On the Usefulness of the French Language in Louisiana)” → “… in the United States” (matching the French title quoted beside it).
- Lessons: “Internee” → “Internet”; “Nos Hommes at Notre Histoire” → “et”; “Gwendolin Midlo Hall” → “Gwendolyn”; “the earliest know Opera performance” → “known opera”; and five grammar slips.
- Italics that started or stopped a letter off (“L<em>es Cenelles l</em>eft” and a dozen like it), book and newspaper titles set in italics where they weren’t, spaces before commas and periods, curly quotes, em dashes for “--”, and en dashes in year ranges.

## Links

- Kept: The Hermitage (thehermitage.com) and the National Park Service’s Chalmette Battlefield (nps.gov) in Lesson 2; All Things Cherokee (it refuses automated checks, but the page is up).
- Removed: the Louisiana State Museum’s old site (lsm.crt.state.la.us), HistoryNet, Hillsdale College, the Yahoo GeoCities *Driftwood* page and the Secretary of State’s old site, which no longer work; the lesson plans’ links to other exhibition pages and their “(link)” placeholders.
- The lesson plans and questions now use the galleries’ names (“Two Foundation Myths” → Conflicting Myths and Loyalties; “The Afro-Creole Press” → The Free Black Press).

## To verify

Left as written:

- “the 1764 transfer” (Louisiana was ceded to Spain in 1762, with the transfer made public in 1764 and completed in 1769).
- Sarah Bernhardt called a “singer” on the opera page (she was an actress).
- “beyond the confident veneer” (the original wording is unclear).
- L’Athénée louisianais founded in 1875, while the declaration shown is dated 1882.
- “Links in the lesson plans connect…” in Dear Educator, though some of those links are gone.

## New wording to check

- The plate titles and summaries in every gallery (for example “A creole mythology”, “Two foundation myths”, “Given as novels, taken for history”, “Swords and pens”, “In a man’s world”).
- The picture titles, taken from the first sentence of each caption and shortened.
- The alt text.
- The cover label (George François Mugnier, *No. 374; Old Spanish House, Royal St.*, about 1880–1910, stereograph, albumen prints).
- The caption for the five Mugnier stereographs in The Creole Family.
- The summaries of the three lessons and the Questions for Comprehension.

## Walls (2026-11-20)

- Every gallery is a wall with a lead picture. Introduction and For Teachers are walls of text plates only.
- A gallery opens on a picture of 720p or more (1280 × 720, either way up) where it has one; only the LDL masters are that large. Four galleries do:

  | Gallery | Lead | Size |
  |---|---|---|
  | Introduction | Mugnier No. 375, Old House, French Quarters | 2560 × 1480 |
  | The Creole Family | Mugnier No. 418, Old Houses, Toulouse & Dauphine (was *La famille créole*, 450px) | 2560 × 1457 |
  | Beyond the “Belle Créole” | Postcard of Sidonie de la Houssaye’s home (was a Queyrouze photograph, 300px) | 1400 × 898 |
  | For Teachers | De la Houssaye’s 1882 school announcement | 1400 × 1504 |

  These fill the screen.
- The other thirteen galleries have no picture that large, so each opens on its case’s first picture (360–500px wide), with “Show whole image” ticked (`field_exhibit_lead_whole`): the lead is shown whole over a blurred wash of itself, not cropped and enlarged. The script decides this from each picture’s size; the dry run lists it for every gallery.
- The cover, Mugnier No. 374 (2560 × 1477), fills the screen, so “Frame the cover” is left off. The script would tick it for a cover under 720p.
