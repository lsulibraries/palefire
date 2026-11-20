# Free People of Color in Louisiana: Revealing an Unknown Past: migration corrections

Exhibition: Free People of Color in Louisiana: Revealing an Unknown Past, the online exhibition of the NEH grant project (2013–2015) of LSU Libraries Special Collections, the Louisiana State Museum, the New Orleans Public Library, The Historic New Orleans Collection and Tulane University’s Louisiana Research Collection.
Old site: the static pages at /sites/all/files/sc/fpoc (2015), copied to twelve Showcase pages under /fpoc (with /special/fpoc), whose characters were mis-encoded. The text is taken from the static pages.
New page: `/v2/special/exhibitions/fpoc`, built by `scripts/palefire_exhibit_fpoc.php` from `scripts/data/fpoc.json`.

## Changed on the page

- Eleven galleries: Introduction; the six chapters of Michael Taylor’s history (Contexts, Early Days, Transition, Golden Age, Decline and Civil War, Legacies); The Collections; Terminology; Resources; About the Project.
- The Collections: one entry per partner, each with a link to its research center, and its 60 collections as paragraphs.
- Resources: the Selected Bibliography is a Sources list; the three lists of links follow.
- About the Project takes in the Grant Team page.
- Pictures: the old pages had four, at 250px. Three came from The Historic New Orleans Collection’s own holdings and aren’t in the digital collection (the Jules Lion lithograph of the cathedral, the portrait of Victor Séjour, and the *Variétés du Carnaval* sheet music), so they are left out. The twelve pictures on the new page all come from the project’s collection in the Louisiana Digital Library, made from its 4,000px masters: Bellazire Meullion’s 1845 receipt (from the old About page, now at full size), the Warburg letter (the cover), Marianne’s petition, the Circe act of freedom, the Durand letter, the Lemelle certification, the receipt to Rose signed by Andrew Jackson, the Durnford memorandum book, the Register of free colored persons (its cover and a page), the 1860 sheriff’s sale broadside and the Desdunes prospectus.
- Lead pictures for the three galleries that had none, each tied to something already on the page: The Collections opens on Eugène Warburg’s 1851 sketch of a marble floor for St. Louis Cathedral, from the Samuel Wilson, Jr. Collection of St. Louis Cathedral Papers, which also holds Jules Lion’s December 1850 offer to paint the cathedral’s ceiling (Lion’s lithograph of the cathedral opened the old exhibition, and Warburg’s letter is the cover). Terminology reuses the page of the Register of free colored persons, whose color column records the glossary’s terms. Resources opens on one of the old exhibition’s own pictures, brought back: Basile Barès’s *Les Variétés du Carnaval* (1875). The old pages showed The Historic New Orleans Collection’s copy; this is Tulane’s, from the Louisiana Sheet Music Collection in the Hogan Archive, rendered from Tulane’s scan at 2,560px. Barès is one of the composers named in Golden Age. Tulane marks the scan “In Copyright”; the music itself, from 1875, is long out of copyright.

## Corrected

- “Ponce de Léon” → “Ponce de León”
- “their experiences..” → “their experiences.”
- “a 25 years-long liaison” → “a 25-year liaison”
- “crystalized” → “crystallized”
- “French speaking-free people of color” → “French-speaking free people of color”
- “Lousiiana” → “Louisiana” (Johanna Lee Davis Smith’s dissertation, in the bibliography)
- “expressed in herein” → “expressed herein” (the NEH notice)
- “àcet État” → “à cet État” (Marigny’s *Réflexions*, in the collection list)
- Curly quotes and apostrophes, and en dashes in number ranges (1718–1803).
- The italics and spacing around *creole*, *criollo* and *crioulo* (Terminology).

## Links

- The Grant Team is listed as it was in 2015, without its e-mail addresses.
- LSU catalog links go to Combined Search; LSU and Louisiana State Museum finding aids (PDFs) still work and are kept.
- The partners’ old catalog and finding aid links no longer work (The Historic New Orleans Collection’s catalog, Tulane’s Archon and Voyager, the New Orleans Public Library’s nutrias.org pages), so they’re gone; each partner’s entry links to its research center instead.
- Resources: the links the Showcase pages had already updated are used (UNC Greensboro, the Amistad Research Center and Le Musée de f.p.c. on the Wayback Machine, Rootsweb). The University of Virginia’s visual record has moved to slaveryimages.org.
- Put back (2026-11-20): each collection’s link to its digitized items. The 2015 site had “View the collection online” under each one, pointing to CONTENTdm, which no longer works; it was taken off between 2016 and 2018. Now “In the Louisiana Digital Library” searches the project’s LDL collection for the collection’s name, for 58 of the 60. Jacques’s 1859 passport and the 1860 registration certificate aren’t in the LDL, so they have none. The bakers’ declarations and passports are searched as the LDL titles them, “Office of the Mayor passports”.
- Put back (2026-11-20): Terminology’s closing pointer to the encyclopedia entry on Creoles, taken off between 2020 and 2023. KnowLA is now 64 Parishes, so it links to 64parishes.org/entry/creoles.

## To verify

Left as written:

- “Institute Catholique” (usually “Institution Catholique des Orphelins Indigents”, or “Institut Catholique”).
- “Henriette Delille … foundress of the Sisters of the Holy Family, the second oldest Catholic religious order for women of color.”
- “Réflexions … Reprèsentans” in the collection list keeps the 1848 title page’s spelling.
- The grant team’s titles are as of 2015.

## New wording to check

- The credit line.
- The twelve captions and alt texts, written from each item’s LDL record.
- The cover’s object label (A. Sampson, Letter introducing the sculptor Eugène Warburg, 1852).
- The Creoles sentence in Terminology, reworded for 64 Parishes.
- The LDL searches are on the collections’ names, so some bring up neighbors too (“Metoyer” papers find each other); each one’s first results are the right items.

## Walls (2026-11-20)

- The Collections and Terminology are walls of text plates. Each partner’s plate opens its list of collections; its opening line now says how many collections it lists and their years (from the collection titles, e.g. “12 collections, 1776–1964”).
- Terminology keeps its opening as the gallery’s text; “Creole”, Controlled Vocabularies and Historical Terminology for Racial Designations and Descriptive Terms are an entry each. The glossary of historical terms stays a single list inside the last one rather than a plate per word.
- Golden Age and Decline and Civil War are walls (2026-11-20): each chapter is four text plates at turns in the argument (Statehood and a booming city; Authors, artists and musicians; Fortunes in business; Beyond New Orleans. The laws close in; Leaving Louisiana; Divided loyalties; A mixed blessing), each with one of Taylor’s own sentences as its summary, and the documents between them. The text is unchanged, only divided; the plate titles are new.
- Resources is a wall of four plates, one per list.
- “Show whole image” is off on every gallery (2026-11-20). The first build ticked it on each lead by default; on a wall it was ignored then, but it now frames the lead over a blurred copy of itself, for pictures too small to fill the screen. These leads are full-size masters, so they stay full screen (`palefire_exhibit_fpoc_lead_whole.php`).
