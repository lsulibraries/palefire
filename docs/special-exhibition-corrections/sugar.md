# Sugar at LSU: A Chronology: migration corrections

Exhibition: Sugar at LSU: A Chronology, LSU Libraries Special Collections, 1995, for the two hundredth anniversary of commercial sugar production in Louisiana. Curated by Christina Riquelmy and Debra Currie, mounted by Emily Robison. Website by Tatiana Shabelnik and Chuck Thomas, with Elaine Smyth; revised in 2002 by Matt Mullenix.
Old site: eight Showcase pages, /sugar/introduction and nodes 275–281 (History to 1700s, The 1800s, 1900s, Today and Tomorrow, Additional Information, Bibliography, Acknowledgements), 29 pictures at 600px (the same files are at /sites/all/files/sc/exhibitions/e-exhibits/sugar).
New page: `/v2/special/exhibitions/sugar`, built by `scripts/palefire_exhibit_sugar.php` from `scripts/data/sugar.json`.

## Changed on the page

- Seven galleries, all walls, each with a lead picture: Introduction; History to the 1700s; The 1800s; The 1900s; Today and Tomorrow; Also on Display (was Additional Information); About the Exhibition (Bibliography and Acknowledgements, with the curators’ and website credits).
- Each of the thirteen cases’ essays is a text plate: its heading, a written Summary, its paragraphs unchanged, then the case’s other items as an “Also in case N” list. The case’s pictures hang after it as entries with short titles, so the object view reads plate, pictures, next plate.
- The Introduction’s footnote (the 1995 physical exhibition) is a paragraph of the Introduction plate and part of the credit line. The credit says the text is from 1995, so its “today” and “this century” read in time.
- Bibliography is a Sources list.

## Pictures

Each picture’s source, best copy first.

| Picture | Old file | Now from |
|---|---|---|
| Cover: first kettle used in making sugar in Louisiana, 1905 | none (case 1 listed a modern photo of the kettle) | LDL lsu-ua-uap:2153, JP2 master 4345px, as 2560px |
| Introduction lead: Audubon Sugar Factory, Baton Rouge, 1926 | none (listed in case 9) | LDL lsu-ua-uap:2129, JP2 master 2941px, as 2560px |
| History to the 1700s lead: Sugar Kettle marker, 1970 | none | LDL lsu-ua-uap:2347, JP2 master 6179px, as 2560px |
| The 1800s lead: Mugnier, No. 518, Levee, Sugar Sheds, & Refinery | sug3.jpg, 600px | LDL lsu-sc-gfm:10, 2560px (the same glass negative) |
| The 1900s lead: Laboratory and Residence, 1899 | sug8a.jpg, 600px | LDL lsu-ua-uap:553 (its Front, uap:554), 1600px |
| Today and Tomorrow lead: first seedling cane, 1907 | none (listed in case 10) | LDL lsu-ua-uap:7 (its Front, uap:8), 1600px |
| About lead: sugar students at work, Nov. 1902 | none (listed in case 8) | LDL lsu-ua-uap:1091 (its Front, uap:1092), 1600px |
| Chemical lab, Audubon Park, 1899 | none (listed in case 8) | LDL lsu-ua-uap:147 (its Front, uap:148), 1600px |
| Experimental cane harvester, 1907 | sug11b.jpg, 600px | LDL lsu-ua-uap:316 (its Front, uap:317), 1600px |
| Thornton cane storage derrick, Geo. M. Murrell’s | none (listed in case 7) | R:\SpecialCollections\Exhibitions\Completed Exhibitions\EXHIB_Of Kin and Cane_spring2013\digitized collections\Gay Photos\Thornton Cane Storage001a.tif, 5853px, as 2560px |
| Also on Display lead: man shaping sugar into cones | sug15a.jpg | old file, 447×600, shown whole |
| The other 25 | sug1a … sug14 | old files, 600px |

- No rescans of the other old pictures were found. Searched: R:\SpecialCollections\Exhibitions (Completed Exhibitions, Online_Exhibitions, the top-level folders; the only sugar-related scans are in EXHIB_Of Kin and Cane_spring2013, which has other Gay family pictures, not sug6b), and the LDL export’s MODS for every LSU collection (Harper’s, Waud, Davidson, Rillieux, Porter, Rochefort, Dureau, Silliman, Hollywood, Southdown, P.O.J., sugar mill, sugarhouse and more). The Coates, Minor and Gay papers’ photographs, the Harper’s engravings and the book illustrations are not in the LDL.
- The Mugnier negative is wider than the old crop; it is used uncropped.
- Framing: every lead and the cover is 1280×720 or larger except Also on Display’s, the old 447×600 file (no larger copy found), so that gallery has Show whole image set: the picture whole over a blur of itself, not enlarged. The cover (2560px) is not framed.
- The old caption dates the laboratory photograph 1899; the LDL has two prints, uap:553 (1899) and uap:2340 (1909, larger). The 1899 record is used.

## Corrected

- “1500’s”, “1700’s”, “1790’s” and the other decades → “1500s”, “1700s”, “1790s”, everywhere.
- “Etienne de Bore” → “Étienne de Boré”, as on the 1970 marker (text, case 1 list, Additional Items).
- “prrcis” → “précis”, “canne `a sucre” → “canne à sucre” (twice), “matire cireuse” → “matière cireuse”, “Dictionnaire raisonne … Neufchatel, Societe” → “raisonné … Neufchâtel, Société”, “Memoires historiques” → “Mémoires historiques”, “iles Antilles de l’Amerique” → “îles Antilles de l’Amérique”, “Traite theorique” → “Traité théorique et pratique” (the book’s full title), “Precis sur la canne” → “Précis sur la canne”, “Dutrune de La Couture” → “Dutrône de La Couture”, “Baume” → “Baumé”, “Bonne Esperance” → “Bonne Espérance”.
- “Lt. Dumont de Montigny.Memoires” → a space after the period; “Statement of the Sugar Crop Made in Louisiana .” → no space; “Sugar Cane : A Treatise” → “Sugar Cane: A Treatise”.
- “Diagram of sugar kettles with furnace; implements used in sugarmaking Sugarcane plant and seed cane. Animal-powered cane mill Sugarhouse interior and plan.” → the four engravings as one list separated by semicolons.
- “Thorton Cane Storage” → “Thornton”, as written on the photograph.
- “William G. Taggert” → “Taggart”, as on the 1907 seedling photograph.
- “Deer, Noel” → “Deerr, Noel” (bibliography, twice; the case lists already had Deerr).
- “frost-resistance, and heartiness” → “hardiness”.
- “Solenopsis invecta” → “Solenopsis invicta”; “Eutheola rugiceps” → “Euetheola rugiceps”; Latin names in italics.
- “Sugar-Cane Disease: A Guide for Field Identification” → “Sugar-Cane Diseases” (Koike’s title).
- “Fire ant attaching sugarcane worm” (alt text) → the caption’s own “attacking a sugarcane borer”.
- “cut cane--slings and derricks--were” → em dashes; “photoperiod control - which … - crosses” → em dashes.
- “Debbie Currie” (Introduction footnote) → “Debra Currie”, as in the Acknowledgements.
- “Raising Cane in Louisiana” and other titles italicized; curly quotes and apostrophes; en dashes in ranges (1846–1924, 1835–1836, 1893–94).
- The caption of sug9b linked to its own image file; the link is gone.
- “Special thanks to Mr. Jean-Guillaume Dumas for use of his sugar cane image in the production of this site” → “of that site” (the image isn’t used here).

## To verify

Left as written:
- “Dr. St. John Poindexter Chilton and Dr. Clayton C. Mooreland”: the second name may be Moreland.
- “80,000 new varieties are grown each year” and the other present-tense figures are 1995’s.
- “the recent closing of the Supreme Sugar Refinery, leaving just two commercial refineries in Louisiana” (1995).
- sug6a’s caption names two photographs (the experiment station and the laboratory interior, 1894); only the interior is pictured.
- sug2a’s caption names three views (the replica, its kettles, the interior); only the replica is pictured.
- Credits naming people for photographs (Jim Zietz, John Wozniak, Joey Quebedeaux, Jeff Hoy) are as the old pages gave them.

## New wording to check

- The title split (“Sugar at LSU” / “A Chronology”), the credit line and the cover’s object label.
- The gallery title changes: “History to 1700s” → “History to the 1700s”, “1900s” → “The 1900s”, “Additional Information” → “Also on Display”, Bibliography and Acknowledgements as About the Exhibition.
- The Introduction’s second paragraph and About the Exhibition’s last paragraph (on the pictures), the plate titles for the Introduction, Rural Life Museum loans and Other Items, every plate’s Summary, the entry titles, the lead captions, the notes in brackets in the case lists, and the alt text for every picture.
