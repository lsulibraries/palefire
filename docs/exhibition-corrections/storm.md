# Tempests: Storms in the Archives: migration corrections

For curators’ review. Old exhibition: `_reference/Oralhistory Exhibitions/presentations/storm/` (live at https://lib.lsu.edu/sites/all/files/oralhistory/presentations/storm/home.html). New text: `scripts/data/tempests.json`, built by `scripts/palefire_exhibit_tempests.php`. The old site was one list of 42 clips; the new page groups 41 of them into five galleries by era, and the 42nd (the Lower Ninth Ward video) became text in About the Exhibition. Where a line below says “gallery / entry”, the entry is named by its new title.

Applied everywhere, not itemized below: straight apostrophes in titles, captions and About became ’; speaker labels changed from “**Name**:” at the start of a line to a bold label in capitals on its own paragraph (“**EARL ROBICHEAUX:**”), with line breaks (`<br><br>`) turned into paragraphs; ALL-CAPS titles in the old side menu became sentence case.

## 1. Corrected

### Entry titles

Old title is the clip page’s heading; where the old side menu said something different, that is given too. Rewordings that go beyond a typo or case fix are also listed under 3.

- The Old Storms, 1893–1926: “a woman and her three children perish in the 1926 storm” → “A woman and her three children perish in the 1926 storm”
- The Old Storms, 1893–1926: “1926 hurricane and the demise of one family's shrimping platforms” → “The 1926 hurricane and the end of one family’s shrimping platforms”
- Audrey and the Marsh, 1950s: “Hurricanes Flossy and Betsy; Riding out storms in the Coast Guard station” → “Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station”
- Audrey and the Marsh, 1950s: “Impact of Hurricane Audrey on marsh grass, erosion” → “The impact of Hurricane Audrey on marsh grass and erosion”
- Audrey and the Marsh, 1950s: “Remembers her Red Cross relief effort experiences after Hurricane Audrey” → “Red Cross relief work after Hurricane Audrey”
- Betsy, 1965: “Long term effects of Betsy on Lower Ninth Ward” (side menu: “Long term effects of Betsy on the Lower Ninth Ward”) → “Long-term effects of Betsy on the Lower Ninth Ward”
- Betsy, 1965: “Hurricanes in the 1960s; Experiencing the eye wall in Betsy” → “Hurricanes in the 1960s; experiencing the eye wall in Betsy”
- Betsy, 1965: “Surviving Hurricane Betsy; The impacts of Camille and Katrina” → “Surviving Hurricane Betsy; the impacts of Camille and Katrina”
- Betsy, 1965: “Riding out Hurricane Besty in Westwego” → “Riding out Hurricane Betsy in Westwego”
- Living with the Coast: “Terrebonne Parish drainage issues during storms over last 150 years” → “Terrebonne Parish drainage issues during storms over the last 150 years”
- Living with the Coast: “Danger of slow moving storms to barrier islands” → “The danger of slow-moving storms to barrier islands”
- Living with the Coast: “Importance of coastal restoration for preserving Louisiana's culture” → “The importance of coastal restoration for preserving Louisiana’s culture”
- Living with the Coast: “Grand Isle Mayor on convincing people to evacuate for a hurricane” → “Grand Isle’s mayor on convincing people to evacuate for a hurricane”
- Living with the Coast: “Improved oyster harvest after Hurricane Andrew” → “An improved oyster harvest after Hurricane Andrew”
- Living with the Coast: “Coastal restoration projects; Transformation of the coastline by Katrina” → “Coastal restoration projects; the transformation of the coastline by Katrina”
- Katrina and Rita, 2005: “Emotional trauma of being displaced” → “The emotional trauma of being displaced”
- Katrina and Rita, 2005: “Evacuation of Riverbend Nursing Home in Kentwood, LA, during Katrina” (side menu: “Evacuation of Riverbend Nursing Home in Kentwood”) → “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina”
- Katrina and Rita, 2005: “Hospital's preparedness for Hurricane Katrina” → “A hospital’s preparedness for Hurricane Katrina”
- Katrina and Rita, 2005: “Loss of livestock after Hurricanes Rita and Ike” → “The loss of livestock after Hurricanes Rita and Ike”
- About the Exhibition: “Lower 9th Ward Oral History Archive Clips” (side menu: “LOWER 9TH WARD ORAL HISTORY ARCHIVE”) → heading “Lower Ninth Ward Oral History Archive”

### Captions

- The Old Storms, 1893–1926 / “Rigaud’s grandmother survived the storm of 1893, but lost her parents” (old page audio.html): “Grand Isle, center, "General Reconnaissance of the Gulf Coast'" 1853. NOAA.” → “Grand Isle, center, “General Reconnaissance of the Gulf Coast,” 1853. NOAA.”
- The Old Storms, 1893–1926 / “The 1926 hurricane and the end of one family’s shrimping platforms” (old page C3.html): “Seabreeze Pass, "Barataria Bay to Terrebonne LA," 1908. NOAA.” → “Seabreeze Pass, “Barataria Bay to Terrebonne LA,” 1908. NOAA.”
- Audrey and the Marsh, 1950s / “The impact of Hurricane Audrey on marsh grass and erosion” (old page C5.html): “Marsh die-back observed in Terrebonne Basin, LA. USGS.” → “Marsh die-back observed in Terrebonne Basin, Louisiana. USGS.”
- Audrey and the Marsh, 1950s / “Drought, fire, and storms in the marshlands around Grand Chenier” (old page C6.html): “Grand Chenier, Mermentau River, "Constance Bayou to Calcasieu Pass," 1928. NOAA.” → “Grand Chenier, Mermentau River, “Constance Bayou to Calcasieu Pass,” 1928. NOAA.”
- Betsy, 1965 / “The seriousness of Betsy, as men prayed” (old page C9.html): “Archbishop Philip Hannan after Hurricane Betsy hits Lousiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.” → “Archbishop Philip Hannan after Hurricane Betsy hit Louisiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.”
- Betsy, 1965 / “Grand Isle rebuilds after Hurricanes Betsy and Katrina” (old page C10.html): “Hurricane Betsy damage in Grand Isle, LA, 1965. U.S. Army Corps of Engineers, Louisiana Digital Library.” → “Hurricane Betsy damage in Grand Isle, Louisiana, 1965. U.S. Army Corps of Engineers, Louisiana Digital Library.”
- Betsy, 1965 / “National Guard relief efforts after Betsy; President Johnson helping evacuees” (old page C19.html): “President Lyndon Johnson speaks with William Marshall who rode out Betsy on St. Claude Avenue. Times Picayune, September 11, 1965.” → “President Lyndon Johnson speaks with William Marshall, who rode out Betsy on St. Claude Avenue. Times-Picayune, September 11, 1965.”
- Betsy, 1965 / “Grandfather rode out Betsy with his dog in the car” (old page C30.html): “Mathew Thiel.” → “Matthew Thiel.”
- Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie” (old page C23.html): “Elevated camp in Cocodrie, LA. 2004. Louisiana State Archives” → “Elevated camp in Cocodrie, Louisiana, 2004. Louisiana State Archives.”
- Living with the Coast / “The danger of slow-moving storms to barrier islands” (old page C24.html): “Storm tracks, 1957-2008. NOAA.” → “Storm tracks, 1957–2008. NOAA.”
- Living with the Coast / “Small storms that hit Grand Isle seem to intensify” (old page C25.html): “Grand Isle 1939 nautical chart from the US Coast & Geodetic Survey. NOAA.” → “Grand Isle, 1939 nautical chart from the U.S. Coast and Geodetic Survey. NOAA.”
- Living with the Coast / “Grand Isle’s mayor on convincing people to evacuate for a hurricane” (old page C28.html): “Post-Katrina Grande Isle, 2005. NOAA.” → “Grand Isle after Katrina, 2005. NOAA.”
- Katrina and Rita, 2005 / “Human kindness kept him from giving up after losing so much in Katrina” (old page C39.html): “Victor Cusher.Photo by David Breidenbach.” → “Victor Cusher. Photo by David Breidenbach.” (the portrait now comes first, the FEMA photo second; that caption is unchanged)

The same captions are reused as the gallery lead captions (see 3).

### Names and credit lines

- Hurricanes Flossy and Betsy: “Russel Crosby” (old credit line and side menu) → “Russell Crosby”, as in the transcript’s own speaker label and the audio file name.
- Evacuating Riverbend Nursing Home: speaker label “Cathy Chauvin” → “KATHY CHAUVIN”, to match the credit line and caption (“Daryl and Kathy Chauvin”).
- Grandfather rode out Betsy: caption “Mathew Thiel.” → “Matthew Thiel.”, to match the credit line and speaker label.
- Returning to New Orleans after Katrina: speaker label “Dr. Frank Minyard” → “FRANK MINYARD” (the credit line keeps “Dr. Frank Minyard”).
- Importance of coastal restoration: “Kerry St. P&#233;” (an HTML code shown in the old text) → “Kerry St. Pé”.
- Learning to evacuate (George Miller): the old credit line repeated the photo credit (“George Miller. Photo by David Breidenbach.”) → credit is now just “George Miller” (see 2).

### Transcripts

- The Old Storms, 1893–1926 / “A woman and her three children perish in the 1926 storm” (old page C2.html): “"Oh no," she said. I can't go,"” → “"Oh no," she said. "I can't go,"”
- Audrey and the Marsh, 1950s / “Drought, fire, and storms in the marshlands around Grand Chenier” (old page C6.html): “trapping.There” → “trapping. There”
- Audrey and the Marsh, 1950s / “Red Cross relief work after Hurricane Audrey” (old page C7.html): “- that were so poor that they couldn't afford a funeral -” → “—that were so poor that they couldn't afford a funeral—”
- Betsy, 1965 / “Long-term effects of Betsy on the Lower Ninth Ward” (old page C11.html): “We sent . . We sent” → “We sent . . . We sent”
- Betsy, 1965 / “Surviving in the floodwaters of Hurricane Betsy” (old page C12.html): “a little bit of a flooding. So” → “a little bit of a flooding." So”
- Betsy, 1965 / “Riding out Hurricane Betsy in Westwego” (old page C18.html): “five of six of them” → “five or six of them” (check against the recording)
- Betsy, 1965 / “Riding out Hurricane Betsy in Westwego” (old page C18.html): “memories - I was” → “memories—I was”
- Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “drainage districts formed. and” → “drainage districts formed, and”
- Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “Gulf of Mexico.Love when” → “Gulf of Mexico. Love when”
- Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years” (old page C22.html): “Gulf of Mexicom hoss!” → “Gulf of Mexico, hoss!”
- Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie” (old page C23.html): “- if you want to build, you got to elevate your camp up -” → “—if you want to build, you got to elevate your camp up—”
- Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture” (old page C27.html): “St. P&#233;” → “St. Pé” (speaker label)
- Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture” (old page C27.html): “things we could use if we weren't restored” → “things we could lose if we weren't restored” (check against the recording)
- Living with the Coast / “Grand Isle’s mayor on convincing people to evacuate for a hurricane” (old page C28.html): “we lost everything".” → “we lost everything."”
- Katrina and Rita, 2005 / “Escaping through the roof in Katrina and being rescued by a Good Samaritan” (old page C20.html): “that was way passed” → “that was way past”
- Katrina and Rita, 2005 / “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina” (old page C35.html): “came though” → “came through”
- Katrina and Rita, 2005 / “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina” (old page C35.html): “I never though ice” → “I never thought ice”

### About the Exhibition

- “Peoples' memories extend” → “People’s memories extend”.
- “their consequences--rural and urban”, “rapid change--sometimes”, “turn up the volume--make some noise” → em dashes (“—”).
- “For more information about the exhibition, visit https://lib.lsu.edu/exhibitions.” → “… visit LSU Libraries Exhibitions.” (linked).
- “please contact Center staff at http://www.lib.lsu.edu/oralhistory/.” → “please contact Center staff.” (“Center staff” links to https://lib.lsu.edu/oralhistory).
- “For details, see : https://lib.lsu.edu/oralhistory/collections” → “For details, see the Williams Center collections.” (linked).
- Collections list: “Pointe Coupee : Voices in the Storm” → “Pointe Coupee: Voices in the Storm”; “"I Remember: An Art Show of Environmental Significance,"" CWPPRA” (doubled closing quote) → ““I Remember: An Art Show of Environmental Significance,” CWPPRA”. The list is now a bulleted list.
- Lower Ninth Ward text: the link on “Lower Ninth Ward Living Museum” (http://l9livingmuseum.org/wp/?page_id=131, now a domain for sale) was removed, and the sentence “If you are interested in gaining access to the database please email contact@livingmuseum.org.” was dropped (see 2).
- The About and Acknowledgements pages are now one gallery, About the Exhibition, with the headings “The collections”, “Lower Ninth Ward Oral History Archive” and “Acknowledgments”.

### Restored to the speaker’s words

These transcript edits changed what the speaker said, so the old wording is back (2026-11-19). Spelling and punctuation fixes stay.

- Betsy, 1965 / “Surviving Hurricane Betsy; the impacts of Camille and Katrina”: “Florida has a hurricanes”, as the speaker said it. The first build had changed it (Florida has hurricanes).
- Betsy, 1965 / “Grandfather rode out Betsy with his dog in the car”: “something bad in in 1927”, as the speaker said it. The first build had changed it (bad in 1927).
- Living with the Coast / “After the storm, moving away or elevating camps in Cocodrie”: “So the answer to that is, elevate you camp”, as the speaker said it. The first build had changed it (elevate your camp).

## 2. To verify

- **Betsy, 1965 / “Learning to evacuate after getting caught once in Hurricane Betsy”.** The old page gives no interviewer, year or accession number for George Miller: its credit line is the photo credit. His photo is by David Breidenbach like the 2007 Pointe Coupee interviews (accession 4700.19xx), but that’s a guess. Credit is the name only until someone supplies the rest.
- **Audrey and the Marsh / “Red Cross relief work after Hurricane Audrey”.** The photo (09_Ida_Turcan.jpg) is captioned “Ida Turcan” on the old page, but it shows a child playing on the checkered tile floor of a house that is gone, with debris and a surviving house behind. The caption was left off; the alt text describes the photo. Who and where is it, and who took it?
- **Katrina and Rita / “Bonding with fellow New Orleans residents at City Hall after Katrina”.** The old page had no photo for Sharon Bart Normand, so this entry is audio and transcript only. Is there a photo of her?
- **The Old Storms / “A woman and her three children perish in the 1926 storm”.** Caption kept as written: “Flood, one year later, in rural Louisiana.” The photo is cropped from a page headed “The Flood of 1927 … A graveyard of farm homes” (the uncropped 8_Foret.jpg), so it shows the 1927 flood, not the 1926 storm. Is the caption clear enough, and what is the source to credit?
- **Name spellings (all three left as chosen in 1).** “Russell” vs “Russel” Crosby; “Kathy” vs “Cathy” Chauvin (the audio file name also says Cathy); “Matthew” vs “Mathew” Thiel. Also: Gwendolyn Ridgley (the audio file says “Gwen Ridgely”), Barbara Terance (the audio file says “Terrance”), Clovis Rigaud (the audio file says “Rigaurd”), and Evelyn Thiel Boreros, labeled “Evelyn Anna Thiel Boreros” in her transcript. These were left as the page text has them.
- **Living with the Coast / “The importance of coastal restoration for preserving Louisiana’s culture”.** “There’s a long list of things we could use if we weren’t restored” was changed to “lose”, since “use” doesn’t make sense. Please check against the recording (42_kerry_st_pe).
- **Living with the Coast / “Terrebonne Parish drainage issues during storms over the last 150 years”.** Credited to “Clifford Smith and Skippy”, but only Clifford Smith speaks. Who is Skippy (full name)? The transcript also keeps a profanity (“what the shit you’re doing to me”); confirm it stays.
- **Living with the Coast / “Small storms that hit Grand Isle seem to intensify”.** Credited to “Roger and Noel Camardelle”, but only Noel Camardelle speaks in the clip.
- **Betsy, 1965 / “Hurricanes in the 1960s; experiencing the eye wall in Betsy”.** Kept as written: “Beulah [1967] and Hilda happened pre-1965.” Hilda was 1964 but Beulah was 1967, so the bracket contradicts the sentence.
- **Katrina and Rita / “Father’s experience in storms gave her strength to survive Katrina”.** Kept as said: “My father lived through the 1900s storm in Galveston” (the Galveston hurricane was 1900).
- **Katrina and Rita / “Returning to New Orleans after Katrina…”.** Kept as transcribed: “we’re not just going to scuff them off” (perhaps “scoff” or “shuffle”).
- **Unresolved brackets in transcripts.** Left as transcribed: “So then the [?] start getting worse” (Dolores Parker); “Francis [?], look at the water” (Lucy Thomas); “what they called a [?] storm” and “come along [at their time?]” (Matthew Thiel); “a house on [Renee?] Street” (Evelyn Thiel Boreros).
- **Credit lines.** Interviewers, years and accession numbers are copied from the old pages and weren’t checked against the catalog. Two old lines had small format slips (no comma before the year for David Richard and Kent Ledoux; a comma instead of a semicolon for Angel Aucoin); the new format is the same for every clip.
- **Audio choice.** Russell Crosby and Robert Gerdes each also have a noise-reduced copy (“_NR”) in the old audio folder that the old pages didn’t use; the new page uses the same files the old pages played.
- **Videos.** Three clips were videos (Yancy Welch; Kerry St. Pé; Buddy Daisy and Earl Melancon). The new page plays their sound only, with a still from each video as the photo. The Lower Ninth Ward clip video (YouTube D17jageqC1k, featuring Karen Frank, Ward “Mack” McClendon, Deborah Harris, Percy Robinson and Elijah Johnson) is now private on YouTube, so it isn’t embedded. Is there a public copy?
- **About the Exhibition.** The Lower Ninth Ward Living Museum’s web address and contact email no longer work. Is there a current address or contact to add?
- **About the Exhibition.** The About text still speaks of the physical 2015 listening station (“Visitors are encouraged to listen to the excerpts and to turn up the volume—make some noise! We also encourage you to speak with staff…”). Kept as written; it may want rewording for the web.

## 3. New wording to check

### Page

- Subtitle: “Two centuries of Louisiana hurricanes in oral history, for the tenth anniversary of Katrina and Rita”
- Credit: “T. Harry Williams Center for Oral History, LSU Libraries Special Collections. Curated by Jennifer A. Cramer and Erin Hess.”
- Year: 2015. Cover image: “Hurricane Betsy flooding as seen from Air Force One, 1965. LBJ Library photo by Yoichi R. Okamoto.” (12_thomas.jpg). The intro is the old About page’s first two paragraphs, unchanged.

### Gallery titles and lead captions

Each gallery’s lead image reuses one of its clip photos with that photo’s caption.

- **The Old Storms, 1893–1926** (3 clips). Lead: 08_foret.jpg, “Flood, one year later, in rural Louisiana.”
- **Audrey and the Marsh, 1950s** (5 clips). Lead: 5_erosion.jpg, “Marsh die-back observed in Terrebonne Basin, Louisiana. USGS.”
- **Betsy, 1965** (12 clips). Lead: 1_doucet.jpg, “Archbishop Philip Hannan after Hurricane Betsy hit Louisiana. CNS photo by Frank J. Methe, Clarion Herald, 1965.”
- **Living with the Coast** (9 clips). Lead: 3_camardelle.jpg, “Grand Isle after Katrina, 2005. NOAA.”
- **Katrina and Rita, 2005** (12 clips). Lead: 39_cusher.jpg, “Lower Ninth Ward, October 1, 2005. FEMA.”
- **About the Exhibition** (text only)

### Reworded entry titles

- “1926 hurricane and the demise of one family's shrimping platforms” → “The 1926 hurricane and the end of one family’s shrimping platforms”
- “Remembers her Red Cross relief effort experiences after Hurricane Audrey” → “Red Cross relief work after Hurricane Audrey”
- “Grand Isle Mayor on convincing people to evacuate for a hurricane” → “Grand Isle’s mayor on convincing people to evacuate for a hurricane”
- “Evacuation of Riverbend Nursing Home in Kentwood, LA, during Katrina” → “Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina”
- “Hospital's preparedness for Hurricane Katrina” → “A hospital’s preparedness for Hurricane Katrina”
- Articles added at the start: “The impact of…”, “The danger of…”, “The importance of…”, “The emotional trauma of…”, “The loss of livestock…”, “An improved oyster harvest…”.

### New captions

- Stills from the three videos: “Yancy Welch, 2012. From the video interview.”; “Kerry St. Pé, 2012. From the video interview.”; “Buddy Daisy and Earl Melancon, 2012. From the video interview.”

### Credit lines

Every clip’s credit was reformatted from “Name by Interviewer, year; accession” to “Name, interviewed by Interviewer, year (accession)”:

- Rigaud’s grandmother survived the storm of 1893, but lost her parents: Clovis Rigaud, interviewed by Earl Robicheaux, 2009 (4700.2499)
- A woman and her three children perish in the 1926 storm: Houston Foret, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2072)
- The 1926 hurricane and the end of one family’s shrimping platforms: Louis Blum, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2067)
- Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station: Russell Crosby, interviewed by Earl Robicheaux, 2009 (4700.2025)
- The impact of Hurricane Audrey on marsh grass and erosion: Allen Ensminger, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2036)
- Drought, fire, and storms in the marshlands around Grand Chenier: David Richard and Kent Ledoux, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2070)
- Red Cross relief work after Hurricane Audrey: Ida Turcan, interviewed by Adele Foster, 1997 (4700.0829)
- Surviving storms by seeking shelter in resilient oak trees: Yancy Welch, interviewed by Susan Testroet-Bergeron, 2012 (4700.2568)
- The seriousness of Betsy, as men prayed: John Doucet, interviewed by Ryan Branton, 2010 (4700.2099)
- Grand Isle rebuilds after Hurricanes Betsy and Katrina: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
- Long-term effects of Betsy on the Lower Ninth Ward: Ida Belle Joshua, interviewed by Nilima Mwendo, 2003 (4700.1684)
- Surviving in the floodwaters of Hurricane Betsy: Dolores Parker, interviewed by Nilima Mwendo, 2003 (4700.1688)
- Evacuating in Betsy to the New Orleans Municipal Auditorium: Lucy Thomas, interviewed by Nilima Mwendo, 2003 (4700.1687)
- Learning to evacuate after getting caught once in Hurricane Betsy: George Miller
- Hurricanes in the 1960s; experiencing the eye wall in Betsy: Robert Gerdes, interviewed by Caroline Gerdes, 2012 (4700.2441)
- Surviving Hurricane Betsy; the impacts of Camille and Katrina: Jane Sedgebeer, interviewed by Caroline Gerdes, 2012 (4700.2452)
- Storms and political representation in Plaquemines Parish: Rose Jackson, interviewed by Jennifer Abraham, 2000 (4700.1359)
- Riding out Hurricane Betsy in Westwego: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
- National Guard relief efforts after Betsy; President Johnson helping evacuees: Erbon Wise, interviewed by Tara Zachary, 1995 (4700.0636)
- Grandfather rode out Betsy with his dog in the car: Matthew Thiel and Evelyn Thiel Boreros, interviewed by Caroline Gerdes, 2012 (4700.2457)
- Terrebonne Parish drainage issues during storms over the last 150 years: Clifford Smith and Skippy, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2081)
- After the storm, moving away or elevating camps in Cocodrie: Houston Foret, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2072)
- The danger of slow-moving storms to barrier islands: Sylvia Landry, interviewed by Earl Robicheaux, 2009 (4700.2029)
- Small storms that hit Grand Isle seem to intensify: Roger and Noel Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2026)
- Constructing homes to handle flooding: Don Gagnon, interviewed by Caroline Gerdes, 2012 (4700.2440)
- The importance of coastal restoration for preserving Louisiana’s culture: Kerry St. Pé, interviewed by Susan Testroet-Bergeron, 2012 (4700.2567)
- Grand Isle’s mayor on convincing people to evacuate for a hurricane: David Camardelle, interviewed by Earl Robicheaux, 2009 (4700.2034)
- An improved oyster harvest after Hurricane Andrew: Buddy Daisy and Earl Melancon, interviewed by Susan Testroet-Bergeron, 2012 (4700.2561)
- Coastal restoration projects; the transformation of the coastline by Katrina: Donald Clement, interviewed by Mark Gibson, 2013 (4700.2355)
- Escaping through the roof in Katrina and being rescued by a Good Samaritan: Gwendolyn Ridgley, interviewed by Myrna Matherne, 2007 (4700.1955)
- Losing everything after Katrina in Lakeview: Patsy Ashton, interviewed by Caroline Gerdes, 2012 (4700.2427)
- The emotional trauma of being displaced: Malane Collins, interviewed by Betty Wooddy, 2007 (4700.1951)
- Returning to New Orleans after Katrina and performing his duties as coroner: Dr. Frank Minyard, interviewed by Angie Juban, 2007 (4700.1956)
- Evacuating with her elderly mother in the days after Hurricane Katrina: Barbara Terance, interviewed by Tatiana Clay, 2007 (4700.1962)
- Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina: Daryl and Kathy Chauvin, interviewed by Blanche Jewell, 2007 (4700.1950)
- Bonding with fellow New Orleans residents at City Hall after Katrina: Sharon Bart Normand, interviewed by Tatiana Clay, 2006 (4700.1964)
- After being rescued with her dog, getting a ride to safety from kind strangers: Victoria Webb, interviewed by Jennifer Abraham, 2007 (4700.1957)
- A hospital’s preparedness for Hurricane Katrina: Angel Aucoin, interviewed by Mary Price, 2007 (4700.1949)
- Human kindness kept him from giving up after losing so much in Katrina: Victor Cusher, interviewed by Blanche Jewell, 2007 (4700.1952)
- Father’s experience in storms gave her strength to survive Katrina: Joan Bostick, interviewed by Caroline Gerdes, 2012 (4700.2431)
- The loss of livestock after Hurricanes Rita and Ike: David Richard and Kent Ledoux, interviewed by Don Davis and Carl Brasseaux, 2009 (4700.2070)

### Alt text (new for every image)

- Rigaud’s grandmother survived the storm of 1893, but lost her parents (35_rigaud.jpg): Detail of an 1853 survey chart of the Louisiana coast, with Barataria Bay and Grand Isle picked out in gold on black.
- A woman and her three children perish in the 1926 storm (08_foret.jpg): Wooden houses standing in floodwater up to their windows, with trees behind.
- The 1926 hurricane and the end of one family’s shrimping platforms (25_blum.jpg): A 1908 nautical chart of the marsh and bays between Barataria Bay and Terrebonne.
- Hurricanes Flossy and Betsy; riding out storms in the Coast Guard station (26_crosby.jpg): Aerial photograph of the Grand Isle Coast Guard station, a large two-story building with a lookout tower, among houses and trees.
- The impact of Hurricane Audrey on marsh grass and erosion (5_erosion.jpg): Aerial view from a small plane over broken marsh, with patches of green grass separated by open water.
- Drought, fire, and storms in the marshlands around Grand Chenier (6_richard_ledoux.jpg): A 1928 nautical chart of the coast at Grand Chenier and the mouth of the Mermentau River.
- Red Cross relief work after Hurricane Audrey (09_ida_turcan.jpg): Black-and-white photograph of a child playing on the checkered tile floor of a house that is gone, with debris and a surviving house behind.
- Surviving storms by seeking shelter in resilient oak trees (40_yancy_welch_still.jpg): Yancy Welch, in a cap and work shirt, gestures as he talks, seated on a leather couch in a kitchen.
- The seriousness of Betsy, as men prayed (1_doucet.jpg): Archbishop Philip Hannan and two other clergymen look out over a collapsed, flooded house from a boat.
- Grand Isle rebuilds after Hurricanes Betsy and Katrina (2_camardelle.jpg): A sepia photograph of a damaged house on bare, sandy ground littered with debris and leaning poles.
- Long-term effects of Betsy on the Lower Ninth Ward (10_joshua.jpg): Newspaper photograph of a National Guard truck driving through deep floodwater past a small boat.
- Surviving in the floodwaters of Hurricane Betsy (11_parker.jpg): A flooded New Orleans street, with cars under water to their roofs and a helicopter overhead.
- Evacuating in Betsy to the New Orleans Municipal Auditorium (12_thomas.jpg): Aerial view of flooded city blocks seen from an airplane, with the plane’s wing in the foreground.
- Learning to evacuate after getting caught once in Hurricane Betsy (19_miller.jpg): George Miller, smiling, in a patterned shirt, standing in a living room.
- Hurricanes in the 1960s; experiencing the eye wall in Betsy (31_gerdes.jpg): Robert Gerdes, in glasses and a plaid shirt, seated outdoors on a porch among trees.
- Surviving Hurricane Betsy; the impacts of Camille and Katrina (32_sedgebeer.jpg): Jane Sedgebeer, arms folded, seated at a kitchen table.
- Storms and political representation in Plaquemines Parish (jackson.jpg): Black-and-white portrait of Rose Jackson, smiling, in a dark jacket.
- Riding out Hurricane Betsy in Westwego (4_camardelle.jpg): Satellite photograph of Hurricane Betsy’s spiral of clouds over the curve of the Earth.
- National Guard relief efforts after Betsy; President Johnson helping evacuees (38_wise.jpg): Newspaper photograph of President Lyndon Johnson leaning close to talk with an older man.
- Grandfather rode out Betsy with his dog in the car (30_thiel.jpg): Matthew Thiel, an older man in glasses and a blue shirt, seated with his arms folded.
- Terrebonne Parish drainage issues during storms over the last 150 years (24_smith_skippy.jpg): Map of the Mississippi River and its tributaries, from Minnesota to the Gulf of Mexico.
- After the storm, moving away or elevating camps in Cocodrie (23_foret.jpg): Camps raised on tall pilings over gray water in Cocodrie.
- The danger of slow-moving storms to barrier islands (28_landry.jpg): Map of the Louisiana coast crossed by colored lines tracing hurricane paths, with a legend by category.
- Small storms that hit Grand Isle seem to intensify (27_camardelle.jpg): A 1939 nautical chart of Grand Isle and Caminada Bay.
- Constructing homes to handle flooding (34_gagnon.jpg): Don Gagnon, seated on a striped couch, holding a framed old photograph.
- The importance of coastal restoration for preserving Louisiana’s culture (42_kerry_st_pe_still.jpg): Kerry St. Pé, seated on a wooden dock with a bayou and cypress trees behind him.
- Grand Isle’s mayor on convincing people to evacuate for a hurricane (3_camardelle.jpg): Aerial view of Grand Isle after Katrina, with wrecked buildings, boats and debris along the water.
- An improved oyster harvest after Hurricane Andrew (41_buddy_daisy_and_earl_melancon_still.jpg): Buddy Daisy and Earl Melancon seated in leather office chairs by a desk, in a wood-paneled office.
- Coastal restoration projects; the transformation of the coastline by Katrina (29_clement.jpg): Two USGS satellite images side by side comparing the marsh at South Pass before and after Hurricanes Katrina and Rita.
- Escaping through the roof in Katrina and being rescued by a Good Samaritan (13_ridgley.jpg): Gwendolyn Ridgley, in a white blouse, seated in a dining room beside a china cabinet.
- Losing everything after Katrina in Lakeview (36_ashton.jpg): Patsy Ashton, smiling, seated by a backyard pool, holding an old photograph of a young woman in a gown.
- The emotional trauma of being displaced (39_collins.jpg): Malane Collins, in a white jacket, standing beside a large colorful painting.
- Returning to New Orleans after Katrina and performing his duties as coroner (14_minyard.jpg): Dr. Frank Minyard seated in front of a round painting of himself playing the trumpet.
- Evacuating with her elderly mother in the days after Hurricane Katrina (17_terance.jpg): Map of the United States showing how many Katrina evacuees each state took in, from 239,000 in Texas to a few hundred in others.
- Evacuating Riverbend Nursing Home to Kentwood, Louisiana, during Katrina (18_chauvin.jpg): Daryl and Kathy Chauvin seated at their dining table, with a vase of flowers and a brick fireplace behind them.
- After being rescued with her dog, getting a ride to safety from kind strangers (21_webb.jpg): Victoria Webb, in a white blouse, seated in an armchair beside a table of framed photographs.
- A hospital’s preparedness for Hurricane Katrina (22_aucoin.jpg): Angel Aucoin, leaning on a carved chair in a room full of antique furniture and lamps.
- Human kindness kept him from giving up after losing so much in Katrina (victor.jpg): Victor Cusher, smiling, gesturing with open hands on a porch.
- Human kindness kept him from giving up after losing so much in Katrina (39_cusher.jpg): A house in the Lower Ninth Ward crushed and torn apart, with a bicycle beside the front steps.
- Father’s experience in storms gave her strength to survive Katrina (33_bostick.jpg): Joan Bostick, in a striped top, standing in a hallway holding a cup.
- The loss of livestock after Hurricanes Rita and Ike (7_richard_ledoux.jpg): Aerial view of cattle crowded onto a narrow strip of high ground surrounded by floodwater.

### About the Exhibition

- New headings: “The collections”, “Lower Ninth Ward Oral History Archive”, “Acknowledgments”.
- Link text: “LSU Libraries Exhibitions”, “Center staff”, “Williams Center collections”.
- Lower Ninth Ward: “Included in the video are Karen Frank, Ward "Mack" McClendon, Deborah Harris, Percy Robinson, and Elijah Johnson.” → “The listening station’s video of clips from the archive featured Karen Frank, Ward “Mack” McClendon, Deborah Harris, Percy Robinson, and Elijah Johnson.” (the video itself is no longer shown).
