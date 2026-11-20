<?php

/**
 * @file
 * AVES: a Summary and Role for each person's biography card.
 *
 *   drush php:script scripts/palefire_exhibit_aves_bio_cards.php
 *   drush php:script scripts/palefire_exhibit_aves_bio_cards.php -- apply=1
 *   drush cr
 *
 * A dry run unless apply=1: it lists what it would set and saves nothing.
 *
 * Fills field_entry_summary and field_entry_role
 * (palefire_exhibit_bio_setup.php) on the 42 AVES entries whose biography
 * shows as a card (150 words or more; Claudio Gay's is shorter and shows
 * in full). Each summary is drafted from that entry's own biography, two
 * sentences of what it says; nothing is added from elsewhere. Curators
 * should read them: change any on the entry's edit form.
 *
 * An entry is found by the start of its heading (the name before the
 * dates). A field that already has a value is left alone, so an editor's
 * wording stands and the script is safe to re-run. Headings not found are
 * listed at the end. Only the card reads these fields
 * (_palefire_exhibit_bio_card() in palefire.theme); the timeline, walls
 * and reading counts don't.
 *
 * Saves the page as a new revision. Undo: revert to the previous revision
 * in the page's Revisions tab, or clear the fields on an entry.
 */

declare(strict_types=1);

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

if (PHP_SAPI !== 'cli') {
  return;
}

$args = [];
foreach ((isset($extra) && is_array($extra)) ? $extra : [] as $arg) {
  if (str_contains((string) $arg, '=')) {
    [$k, $v] = explode('=', (string) $arg, 2);
    $args[trim($k)] = trim($v);
  }
}
$apply = ($args['apply'] ?? '') === '1';
$say = fn (string $line) => print(($apply ? '' : '[dry run] ') . $line . PHP_EOL);

foreach (['field_entry_summary', 'field_entry_role'] as $name) {
  if (!FieldConfig::loadByName('paragraph', 'exhibit_entry', $name)) {
    throw new \RuntimeException("$name not found on Exhibition: Entry. Run palefire_exhibit_bio_setup.php first.");
  }
}

// Start of the heading => [summary, role].
$cards = [
  'Francisco Hernández' => [
    'On five journeys out of Mexico City he described 229 birds under their Nahuatl names, some of which, like motmot, are still in use. For two centuries his work was the only authority on Mexican animals.',
    'Physician and naturalist',
  ],
  'Georg Markgraf' => [
    'Brought to Dutch Brazil in 1638 by its governor, Johan Maurits, he spent six years mapping, keeping weather records and describing only what he had seen. Toucan, jacana and ani are among the names that come from him.',
    'Naturalist, astronomer and cartographer',
  ],
  'Juan Ignacio Molina' => [
    'A Chilean-born Jesuit whose natural history of Chile described thirty-three birds. Expelled with his order in 1767, his notes confiscated, he spent the rest of his life in Bologna.',
    'Jesuit naturalist',
  ],
  'Louis Jean Pierre Vieillot' => [
    'He described 387 species still recognized today, more than anyone but Linnaeus, many from specimens in the Paris Museum. He fled Saint Domingue, lost his family at sea, and died poor and blind in Rouen.',
    'Ornithologist',
  ],
  'Félix de Azara' => [
    'Sent to South America in 1781 to settle a border, the Spanish army engineer was left with twenty idle years and filled them with birds. His careful notes on their habits were later praised by d’Orbigny, Darwin and Hudson.',
    'Army engineer and naturalist',
  ],
  'Prince Maximilian of Wied-Neuwied' => [
    'A Prussian prince and soldier who explored Brazil’s Atlantic Forest from 1815, collecting tanagers, hummingbirds and parrots and recording Indian words. Arrested as a suspected rebel, he went home, and later traveled the upper Missouri with Karl Bodmer.',
    'Naturalist and explorer',
  ],
  'Johann Baptist von Spix' => [
    'A Bavarian zoologist who crossed almost 4,000 miles of Brazil with the botanist Martius between 1817 and 1820. Their collections, many new to science, gave him his Avium species novae; the journey broke his health, and he died at forty-five.',
    'Zoologist and curator',
  ],
  'Johann Natterer' => [
    'A falconer’s son trained in taxidermy, he went to Brazil with an Austrian expedition in 1817 and stayed eighteen years, long after it was called off. He brought back 12,293 birds and a vast store of other specimens and objects.',
    'Collector and taxidermist',
  ],
  'Jean Théodore Descourtilz' => [
    'He made the most splendid of all books on Neotropical birds, Oiseaux brillants du Brésil, yet almost nothing is known of his life. He died in Brazil in 1855 after taking a preparation he was testing as a medicine for birds.',
    'Naturalist and illustrator',
  ],
  'Alcide d’Orbigny' => [
    'Sent to South America by the Paris museum in 1826, he spent three years in Bolivia and crossed the Andes five times. He was the first European naturalist there, and with Lafresnaye he described seventy-eight new birds from his collections.',
    'Naturalist and paleontologist',
  ],
  'John Gould' => [
    'A self-made publisher of lavish bird books, snubbed by the establishment for having started as a bird-stuffer. He wrote The Birds of Australia, saw what Darwin’s Galápagos finches were, and for the Neotropics kept to the most glittering families.',
    'Ornithologist and publisher',
  ],
  'Charles Darwin' => [
    'The Galápagos finches carry his name, but they did little to shape his ideas on evolution. Domestic birds, above all the pigeons he bred himself, did more.',
    'Naturalist',
  ],
  'Philip Henry Gosse' => [
    'A naturalist of eager enthusiasms, from birds in Jamaica to sea anemones and the first practical marine aquariums, admired by Darwin for his observation. His Omphalos, reconciling the Bible with geology, put him at the heart of the Victorian crisis of faith.',
    'Naturalist and writer',
  ],
  'Robert Schomburgk' => [
    'Robert explored the rivers of Guyana for the Royal Geographical Society and found the giant Victoria regia water lily; shocked by slave raids, he surveyed British Guiana’s border. His brother Richard, a botanist, collected thousands of specimens on the survey.',
    'Explorer and surveyor; botanist',
  ],
  'Johann Jacob von Tschudi' => [
    'A Swiss student of Agassiz, he spent four years in Peru from 1838, collecting animals and studying the Inca past. He wrote the first comprehensive book on Inca archaeology and a Quechua dictionary, and became Swiss ambassador in Vienna.',
    'Naturalist, explorer and diplomat',
  ],
  'Francis L. de Laporte' => [
    'Born in London, he led a French expedition from Rio de Janeiro to Lima and down the Amazon from 1843 to 1847, losing most of his specimens. Later a consul in Bahia, Siam and Melbourne, he was fooled by a fish made from a mullet, an eel and a platypus.',
    'Naturalist, explorer and consul',
  ],
  'Hermann Burmeister' => [
    'A German professor who made two collecting journeys to South America and brought back over 100,000 specimens. In 1861 he left Germany for good to run the museum in Buenos Aires, built it into one of the continent’s finest, and was given a state funeral.',
    'Zoologist and museum director',
  ],
  'Philip Lutley Sclater' => [
    'A pillar of British ornithology: founder of The Ibis, secretary of the Zoological Society for 43 years, author of over 1,200 papers. His specialty was Neotropical birds, and he first drew the world’s biogeographical zones, the Neotropics among them.',
    'Zoologist and editor',
  ],
  'Frederick Ducane Godman' => [
    'Friends from Cambridge, two wealthy gentleman naturalists who collected in Central America from 1858 to 1865 and trained locals to go on collecting for them. Their collection, given to the British Museum, held about 85,000 birds.',
    'Naturalists and collectors',
  ],
  'Charles B. Cory' => [
    'A rich and many-gifted founder of the American Ornithologists’ Union who gave his 19,000 bird specimens to the Field Museum. When he lost his fortune he took a salaried job there and began the Catalogue of Birds of the Americas.',
    'Ornithologist, Field Museum',
  ],
  'W.H. Hudson' => [
    'Raised on the pampas near Buenos Aires, he collected birds for the Smithsonian before leaving for England in 1874. A field naturalist, not a museum man, he became famous for Green Mansions and never stopped missing the Argentina of his youth.',
    'Naturalist and writer',
  ],
  'Robert Ridgway' => [
    'Taken up by Spencer Baird as a teenager, he headed the Smithsonian’s bird department from 1880 until his death, a shy but dominant force in American ornithology. In his last years he worked from Bird Haven, his preserve in Illinois.',
    'Curator of birds, Smithsonian',
  ],
  'Frank M. Chapman' => [
    'He left banking in 1886 to study birds and spent his career at the American Museum of Natural History, growing its collection from 50,000 skins to over 800,000. He did seminal work on the birds of Colombia and Ecuador and pioneered habitat groups.',
    'Curator of birds, American Museum of Natural History',
  ],
  'George K. Cherrie' => [
    'An Iowan who took over 125,000 specimens on forty expeditions, mostly in South America, and went with Theodore Roosevelt down the River of Doubt. Frank Chapman called him the prince of tropical American bird collectors.',
    'Collector and naturalist',
  ],
  'Leo E. Miller' => [
    'A young collector from Indiana who worked the Andes for Frank Chapman from 1911 to 1917 and joined Roosevelt’s Brazilian expedition. He wrote vividly of the field, then of lost worlds in novels, and settled in Stamford breeding gladioli.',
    'Collector, American Museum of Natural History',
  ],
  'Emilie Snethlage' => [
    'Among the first women in Germany to earn a PhD, she headed a major South American museum and was Brazil’s only woman field ornithologist. She explored the most inhospitable places, and lost a finger to a piranha.',
    'Ornithologist and museum director',
  ],
  'William Beebe' => [
    'A prolific author whose Monograph of the Pheasants is one of the great bird books of the century, and who later went down in the first deep-diving bathysphere. In the tropics he studied a few acres intensively, every web of life in them.',
    'Naturalist, New York Zoological Society',
  ],
  'Carl Eduard Hellmayr' => [
    'The world’s leading authority on Neotropical birds never visited the tropics. He worked from museums in Munich and Chicago, and took over Cory’s fifteen-volume Catalogue of Birds of the Americas.',
    'Ornithologist, Field Museum',
  ],
  'M. A. Carriker' => [
    'Unlike most museum collectors, he went to Colombia in 1911 and stayed most of his life, marrying there and taking his wife on his expeditions. He collected over 75,000 birds and had an uncanny gift for finding species others had missed.',
    'Collector',
  ],
  'Elsie M. B. Naumburg' => [
    'She began as a part-time volunteer in the American Museum’s bird department and became its benefactor, paying for expeditions that brought in some 13,000 specimens. Her bequest endows the Frank M. Chapman Memorial Fund.',
    'Systematist and benefactor',
  ],
  'Robert Cushman Murphy' => [
    'A year on one of the last whaling ships under sail, from 1912, began his lifelong study of seabirds and led to Oceanic Birds of South America. He later chaired the American Museum’s Department of Birds, by his own account liking birds better than people.',
    'Ornithologist, American Museum of Natural History',
  ],
  'John T. Zimmer' => [
    'A quiet, almost reclusive scholar who gave his life to sorting out the relationships of South America’s birds. His expertise was Peru, where he collected 1,497 specimens on the Marshall Field Expedition of 1922–23.',
    'Ornithologist, American Museum of Natural History',
  ],
  'Alexander Wetmore' => [
    'He named 189 birds and had more than fifty named for him. Secretary of the Smithsonian from 1944 to 1952, he still found time for avian paleontology, a field with few followers.',
    'Ornithologist, Secretary of the Smithsonian',
  ],
  'François Haverschmidt' => [
    'A Dutch judge who moved to Suriname in 1946 for the chance to study its little-known birds. His court duties took him to remote districts, and as acting governor he set up the country’s first nature preserves in 1966.',
    'Judge and ornithologist',
  ],
  'William Phelps, Sr.' => [
    'Father and son built the Colección Ornitológica Phelps in Caracas, perhaps the finest single-country bird collection in the world. William Sr. paid for it from his businesses; Billy led many of its hundred-plus expeditions, fifteen of them to the tepuis.',
    'Patron and ornithologists',
  ],
  'Alexander Skutch' => [
    'A botanist turned ornithologist who settled on a farm in Costa Rica in 1941 and watched its birds for the rest of his long life. Much of what is known about the lives of some 300 Neotropical birds comes from his notes.',
    'Naturalist and writer',
  ],
  'Eugene Eisenmann' => [
    'A lawyer who retired early to become a research associate at the American Museum, he wrote little but answered letters from all over the Neotropics. Books by Skutch, Ridgely and others are dedicated to him.',
    'Lawyer and ornithologist',
  ],
  'Rodolphe Meyer de Schauensee' => [
    'A gentleman ornithologist at the Academy of Natural Sciences of Philadelphia, his base for fifty years, though he set foot in South America only once. He brought the Academy large collections from every Andean country but Argentina.',
    'Ornithologist, Academy of Natural Sciences',
  ],
  'Maria Koepcke' => [
    'A German biologist who moved to Peru in 1949; with her husband she made their Lima home, Casa Humboldt, the gathering place for ornithologists working in Peru. She died in a plane crash in 1971, which her daughter survived.',
    'Ornithologist',
  ],
  'Helmut Sick' => [
    'A German who went to Brazil in 1939, spent the war years interned, and then twelve years as naturalist on expeditions opening central Brazil. He stayed for life and became the country’s foremost ornithologist.',
    'Ornithologist',
  ],
  'Ted Parker' => [
    'Called the most gifted field ornithologist of the century, he could identify almost any Neotropical bird by its song. He changed how scientists study these birds, and died in a plane crash during a survey in Ecuador.',
    'Field ornithologist',
  ],
  'Miguel Alvarez del Toro' => [
    'A self-taught naturalist who wrote on the animals of Chiapas and founded a zoo in Tuxtla Gutiérrez for its native animals alone. His patient work saved El Triunfo, the cloud forest of the horned guan, as a reserve.',
    'Naturalist and conservationist',
  ],
];

$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('title', 'AVES')
  ->execute();
if (count($ids) !== 1) {
  throw new \RuntimeException('Expected one Modular page titled "AVES", found ' . count($ids) . '.');
}
$node = Node::load(reset($ids));

$found = [];
$set = 0;

$fill = function (FieldableEntityInterface $paragraph) use ($cards, $say, &$found, &$set): void {
  if ($paragraph->bundle() !== 'exhibit_entry') {
    return;
  }
  $heading = trim((string) ($paragraph->get('field_block_title')->value ?? ''));
  foreach ($cards as $start => [$summary, $role]) {
    if (!str_starts_with($heading, $start)) {
      continue;
    }
    $found[$start] = TRUE;
    $changes = [];
    foreach (['field_entry_summary' => $summary, 'field_entry_role' => $role] as $name => $value) {
      if ($paragraph->get($name)->isEmpty()) {
        $paragraph->set($name, $value);
        $changes[] = ($name === 'field_entry_role' ? 'Role' : 'Summary') . ' “' . $value . '”';
      }
    }
    if ($changes) {
      $paragraph->setNeedsSave(TRUE);
      $set++;
      $say("$heading: " . implode('; ', $changes));
    }
    else {
      $say("$heading: already set, left alone.");
    }
    return;
  }
};

// Every paragraph under a block list, depth first; a changed paragraph marks
// its parents, so the nested ones are saved (as in
// palefire_exhibit_aves_typos.php).
$descend = function (FieldableEntityInterface $paragraph) use (&$descend, $fill): bool {
  $fill($paragraph);
  foreach (['field_guide_blocks', 'field_gallery_items'] as $name) {
    if ($paragraph->hasField($name)) {
      foreach ($paragraph->get($name)->referencedEntities() as $child) {
        if ($descend($child)) {
          $paragraph->setNeedsSave(TRUE);
        }
      }
    }
  }
  return $paragraph->needsSave();
};

foreach ($node->get('field_scroll_section')->referencedEntities() as $gallery) {
  $descend($gallery);
}

foreach (array_keys($cards) as $start) {
  if (empty($found[$start])) {
    $say("Not found: no entry heading starts with “{$start}”.");
  }
}
if ($apply && $set) {
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('AVES: biography card summaries and roles (palefire_exhibit_aves_bio_cards.php).');
  $node->save();
}
$say($apply ? "Saved $set entr" . ($set === 1 ? 'y' : 'ies') . '.' : "Would set $set entr" . ($set === 1 ? 'y' : 'ies') . '. Nothing saved. Run again with apply=1.');
