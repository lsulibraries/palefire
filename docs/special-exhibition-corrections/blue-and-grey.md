# Blue and Gray for Boys and Girls: corrections and sources

Built by `scripts/palefire_exhibit_blue_and_grey.php` from `scripts/data/blue-and-grey.json`, unpublished, at /v2/special/exhibitions/blue-and-grey (the alias keeps the old site’s “BlueandGrey”; the title is the exhibition’s own spelling, “Gray”).

## Where the text came from

- The old online exhibition: /BlueandGrey/introduction and fourteen sibling pages (node/299–313): Introduction, Cases 1–12, Links and Author Profiles. Listed on /exhibitions as “Blue and Grey for Boys and Girls” (“Highlights titles from the Michael Lehman Williamson Collection of Civil War Books for Young People”).
- Five of the fifteen pages (Case 1, Case 2, Case 4, Case 12 and Author Profiles, node/300, 301, 303, 311 and 313) return “The website encountered an unexpected error” on the live site. The pages that still open are 7–15 MB each: every non-breaking space and dash was mis-encoded again on each save, until one character became hundreds of kilobytes of “Ã‚Ã‚…”. The broken five are probably the same, past a size limit. Not in the Wayback Machine.
- Their text comes from the exhibition’s own label files on R:, in `Completed Exhibitions\EXHIB_Old Times There_Civil War_Summer 2012\Old_Times_Civil_War_2012\Blue and Gray 2012`:
  - `Wall_text_1860-1865.docx`: the case texts and their sources, all twelve periods. Where an online page still opens, its text matches this file word for word.
  - `Blue and Gray for Boys and Girls.docx` (July 24, 2012): the online exhibition’s book lists, case by case, including Cases 1, 2, 4 and 12.
  - `Author Profiles.docx`: the author profiles with their numbered sources.
  - `intro_wall_label.docx`: the introduction. The live Introduction page has no text, only a 300 px drawing, so the 2012 wall label stands in.
- Case 4’s books are the ones the July 2012 file lists after Case 3’s Myriopticon (Coffin to Forbes, 1883–1899); the file has no “Case 4” heading. Case 4’s text is the first “1878–1900” wall text (the second is Case 5’s, which the live page confirms).
- Case 12’s book list is the July 2012 online list (22 books). `cases11 and 12.docx` has a longer list for the 2012 gallery display (to 2011); it isn’t used.
- Links: the old Links page.

## Pictures

| Picture | Source |
| --- | --- |
| 12 case photographs (`case-01-display.jpg` … `case-12-display.jpg`), 750 px | The old pages (`/sites/default/files/inline-images/case1b.jpg` … `case12b.jpg`) |
| 61 book covers and title pages, 2,560 px | The 2012 online exhibition’s scans, `EXHIB_Old Times There_Civil War_Summer 2012\online\books\<call number>_<title>\…001.tif` (3,000–10,000 px) |
| Drummer Boy p. 13; Youth’s History frontispiece; Kate Morgan p. 155; Dora Darling p. 72; Cudjo’s Cave cover; Hero Boy frontispiece; Winning His Way p. 194; Two Little Confederates pp. 76, 159, 179 | The 2012 exhibition’s edited scans, `…\Blue and Gray for Boys and Girls\case1_…_edit.tif` to `case4_…_edit.tif` (1,600–2,400 px) |
| Sherman cover | `online\books\E467.1S55F69…\…002.tif` (the …001 scan is the plain back cover) |
| Abraham Lincoln, the Backwoods Boy | `online\books\E457A39Lincoln(compact)…\…002.tif` (the …001 scan is almost black) |
| I Want My Sunday, Stranger!; Frederick Douglass, in His Own Words | The 2012 exhibition prints, `…\Blue and Gray for Boys and Girls\Beatty_I Want My Sunday-Print.tif`, `Russell_Frederick Douglass-Print.tif` (3,000 × 4,800) |
| Myriopticon box and a scene | `…\Blue and Gray for Boys and Girls\myriopticon\box cover.jpg`, `1064346a_2.jpg` (700 and 982 px; the largest there are) |
| The Brownies’ Book, July 1920 | `…\Blue and Gray for Boys and Girls\0205q.jpg` (508 × 691). Not an LSU book: the old page credits the Schomburg Center |
| Boys and Girls Stories of the War, cover | Duke University Libraries’ copy, digitized by the Internet Archive (`boysgirlsstories01boyk`, page n0, 1,333 × 2,195). The old page used Duke’s image; LSU has the book only on microfilm |

Masters: 2,560 px on the long side at most (never enlarged), sRGB JPEG quality 85, metadata stripped, in `C:\Users\kylet\Downloads\blue-and-grey-web\masters` (86 files).

Not used: the old Introduction’s 300 px drawing (`Sherman_1.jpg`, too small; the Sherman cover opens the Introduction instead); the Harper’s Weekly Fort Sumter and Andersonville prints in the 2012 folder (not on the online pages).

The Louisiana Digital Library has none of these books (the Williamson Collection isn’t digitized there), so no LDL images are used.

Books with no scan anywhere, listed in their case’s “Also in the case” plate: Texar’s Revenge (Case 4); Rock of Chickamauga, Brave and Bold Weekly (6); Stories of Dixie (7); Lighthorse Harry’s Boy, Hannah Courageous (8); Walt Whitman, Builder for America; Glory, Hallelujah! (9); Rat Hell (10); Double Time, Marching Through Culpeper (12). Most can be seen in the case photographs.

## Layout

- 15 galleries, all walls with a lead picture: Introduction, Cases 1–12, Author Profiles, Links.
- Each case: its wall text as a text plate (with its Sources), its books as tiles (cover or illustration, with the catalog record and the old note), an “Also in the case” plate for the books with no picture, and the photograph of the case.
- Case 9 had no wall text, so it has no plate.
- Author Profiles: one name tile per author (role: dates · state), under “Nineteenth century” and “Twentieth century” as in the 2012 file. Each profile ends with its numbered source from the file.
- Links: three plates, one per heading.
- Cover: General Lee and Santa Claus (1867), 2,013 × 2,560, with its object label. It is 720p or larger, so “Frame the cover” is off and it fills the screen.
- Leads: every gallery’s lead is a 2012 scan of 720p or larger (the smallest is Dora Darling p. 72, 1,413 × 1,880), so none has “Show whole image” ticked; all fill the screen. The script would tick it for a lead under 1280 × 720.

## Corrected

| Before | After | Where |
| --- | --- | --- |
| Bellock &amp; Co. | Blelock &amp; Co. | Case 3, General Lee and Santa Claus (the 2012 label file has Blelock) |
| Williamson CollectionE 4671 L4 C54 1867 | E467.1 .L4 C54 1867 | Case 3 |
| (Reissued in 1996Ã‚…&cent;…see Case 12) | Reissued in 1996 (see Case 12). | Case 3; same for On the Blockade, Case 5 |
| Fahs … University of North Carolina Press, 1990 | 2001 | Case 7 sources |
| Birth of a Nation | <em>The Birth of a Nation</em> | Case 7 |
| W. E. B. Dubois | W. E. B. Du Bois | Case 7 |
| The Brownie’s Book | <em>The Brownies’ Book</em> | Case 7 |
| Schomberg Center for the Study of Black Culture | Schomburg Center for Research in Black Culture | Case 7 |
| Zienert, Karen | Zeinert, Karen | Case 12 |
| Naden, Corinne, J. | Naden, Corinne J. | Case 12 |
| Robert. E. Lee | Robert E. Lee | Allen Tate |
| Matthew Brady | Mathew Brady | Introduction |
| Bell South | BellSouth | Introduction |
| Haskin’s | Haskins’s | James Haskins |
| Between Two Fires: Black Soldiers and the Civil War | … in the Civil War | Joyce Hansen (the book’s title, as in Case 12) |
| the Newberry Award-winning <em>Across Five Aprils</em> | <em>Across Five Aprils</em>, a Newbery Honor book | Irene Hunt (it was a 1965 Newbery Honor book, not the Medal) |
| in 1950 he claimed the same prize | in 1951 | Carl Sandburg (Pulitzer Prize for Poetry, 1951) |
| Virginia Tech University | Virginia Tech | James I. Robertson, Jr. |
| on assignmentfor Frank Leslie’s Illustrated Magazine | on assignment for <em>Frank Leslie’s Illustrated Newspaper</em> | Case 4, Sherman |
| the plantations system | the plantation system | Case 5, the Lively quotation (as Lively wrote it) |
| Marten, James ed. … Scholarly Resource Books | Marten, James, ed. … Scholarly Resources Books | Case 1 sources |

Also, not listed one by one: curly quotes and apostrophes; en dashes in ranges (1861–1865) and em dashes for spaced hyphens; “ ,” (Fahs, 2001)” punctuation moved outside the quotation; book titles in italics; call numbers in catalog form (PS1449 .C475 D78 for “PS 1449 C475 D78”); “twentieth century” spelled out in running text; the numbered book lists become one tile per book.

## Links

- Kept: The Civil War in Children’s Literature (Carol Hurst); Baldwin Library (now baldwin.uflib.ufl.edu); de Grummond Collection (now degrummond.org); University of Minnesota (now its Children’s Literature Research Collections page).
- Through the Wayback Machine: Mid-Nineteenth Century American Children’s Magazines and Nineteenth-Century American Children &amp; What They Read (merrycoz.org now redirects to its site map); Casting Characters (Dartmouth moved its Library Bulletin).
- Dropped, dead or pointing at a home page: Children’s Literature (uwaterloo.ca/arts), Children’s Literature, Chiefly from the Nineteenth Century and University of South Carolina Rare Books (both the sc.edu libraries home page), Classics for Young People (Calgary, 404), Culture, Ideology, and Children’s Literature (Winnipeg, no response), 19th Century Girls’ Series (readseries.com, no response), Nonfiction and Reference Resources for Children (visi.net), Once Upon a Time (bsu.edu), World of the Child (Delaware, now only its exhibitions home page).

## To verify

- The year, 2012: the online text and its book lists date from July 2012 (the summer 2012 Civil War exhibitions in Hill). The case photographs look older (Case 12 stops at 2001, and the author profiles’ sources date from 2001); the first showing may have been about 2002.
- The credit: “Leah Wood Jewett, Curator / Exhibitions Coordinator” closes the 2012 intro label; the original curator of the cases isn’t named anywhere.
- Hannah Courageous: “Williamson Collection S 3523 O4711 H36” (probably PS3523).
- Case 3 and Case 5 say General Lee and Santa Claus (1996) and On the Blockade (2001) were reissued “see Case 12”, but Case 12’s list includes neither.
- “Standing Like a Stonewall” (the book is titled Standing Like a Stone Wall) and “Medical Practices of the Civil War” (Medical Practices in the Civil War): left as the 2012 list has them.
- Robertson: “Chairman of the Civil War Centennial Commission” (he was its executive director) and “(1930– )” (he died in 2019).
- Tate: “One of the ‘Fugitive Poets’ of the 1930s” (the Fugitives were a 1920s group).
- The Brownies’ Book: the 2012 file credits “Library of Congress” for the cover image, the old page the Schomburg Center.
- Boys and Girls Stories of the War: the Microfilm 4106 reel number.

## New wording to check

- Subtitle “Civil War Books for Young People”, the credit line and the cover’s object label.
- The eleven case plate headings (Drummer boys; The Confederate juvenile; War-weary readers; An explosion of war books; Adventure series for boys; Brothers divided; Books for Black children; Depression and world war; A more realistic hero; Many perspectives; Old classics reissued), the Introduction’s two (Blue and Gray for Boys and Girls; The Williamson Collection) and the Links three. Each plate’s summary is the text’s own first sentence, except the Introduction’s and the Links’.
- The last Introduction sentence (“The exhibition is arranged in twelve cases…”).
- The “Also in the case” plates, the case photograph captions, the lead captions, and the 86 alt texts (written from the scans; the display photographs’ alt text is generic).
- Author roles (dates · state) and the “Source:” lines, shortened from the 2012 file’s footnotes. Hunt’s source was a now-dead edupaperback.org page; Hansen’s a Scholastic page.
