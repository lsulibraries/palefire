<?php

/**
 * @file
 * Demo guide: rebuilds the Systematic Reviews LibGuide as an unpublished
 * Modular page in guide mode. Run palefire_guide_setup.php first.
 *
 *   drush php:script themes/custom/palefire/scripts/palefire_guide_demo.php
 *   drush cr
 *
 * Optional arguments after "--":
 *   format=<text format>  Text format for the guide's text (default:
 *                         full_html, else basic_html). The text has tables,
 *                         so pick a format that allows <table>.
 *   path=<alias>          URL alias, e.g. path=/v2/guides/systematic-reviews
 *
 * Creates:
 * - About 110 Resource nodes (published), each found first by its link so
 *   re-running never duplicates one. LSU-proxied links are stored without
 *   the libezp prefix and with "Requires LSU login" ticked.
 * - One Modular page, "Systematic Reviews", UNPUBLISHED, with 10 guide
 *   sections. It stops if a page linked to the same LibGuide exists.
 * - Links the guide owner (a Staff node titled like "Randa … Morgan"), the
 *   subjects (existing terms with matching names) and the intro video (an
 *   existing or new Remote video media item) when they can be found; the
 *   output lists anything it skipped.
 *
 * Content is paraphrased from https://guides.lib.lsu.edu/Systematic_Reviews
 * (updated Oct 6, 2026). Gaps the LibGuide text didn't include are marked
 * "[Editor: …]" in the page so they are easy to find.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

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

foreach (['resource' => 'node type', 'guide_section' => 'paragraph type'] as $id => $what) {
  $exists = $what === 'node type'
    ? \Drupal::entityTypeManager()->getStorage('node_type')->load($id)
    : \Drupal::entityTypeManager()->getStorage('paragraphs_type')->load($id);
  if (!$exists) {
    throw new \RuntimeException("$what '$id' not found. Run palefire_guide_setup.php first.");
  }
}

$source_url = 'https://guides.lib.lsu.edu/Systematic_Reviews';
$found = \Drupal::entityQuery('node')->accessCheck(FALSE)
  ->condition('type', 'modular_page')
  ->condition('field_guide_source.uri', $source_url)
  ->range(0, 1)->execute();
if ($found) {
  throw new \RuntimeException('A Modular page for this LibGuide already exists (node ' . reset($found) . '). Delete it first to rebuild the demo.');
}

$formats = filter_formats();
$format = $args['format'] ?? (isset($formats['full_html']) ? 'full_html' : (isset($formats['basic_html']) ? 'basic_html' : filter_default_format()));
if (!isset($formats[$format])) {
  throw new \RuntimeException("Text format '$format' not found.");
}
echo "Text format: $format" . PHP_EOL;
$skipped = [];

// Resources. ---------------------------------------------------------------

const PROXY = 'https://libezp.lib.lsu.edu/login?url=';
$resources = [];

/**
 * Finds or creates a Resource by link and returns its id.
 */
$r = function (string $key, string $title, string $url, string $type, string $desc = '', string $citation = '', array $labels = []) use (&$resources): void {
  $proxy = FALSE;
  if (str_starts_with($url, PROXY)) {
    $url = substr($url, strlen(PROXY));
    $proxy = TRUE;
  }
  $ids = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', 'resource')
    ->condition('field_resource_link.uri', $url)
    ->range(0, 1)->execute();
  if ($ids) {
    $resources[$key] = (int) reset($ids);
    return;
  }
  $node = Node::create([
    'type' => 'resource',
    'title' => $title,
    'status' => 1,
    'field_resource_link' => ['uri' => $url],
    'field_resource_type' => $type,
    'field_resource_desc' => $desc,
    'field_resource_citation' => $citation,
    'field_resource_labels' => array_map(fn ($l) => ['value' => $l], array_slice($labels, 0, 3)),
    'field_resource_proxy' => $proxy,
  ]);
  $node->save();
  $resources[$key] = (int) $node->id();
};

// Overview.
$r('prisma2020', 'The PRISMA 2020 statement: an updated guideline for reporting systematic reviews', 'https://www.bmj.com/content/372/bmj.n71', 'article', 'Replaces the 2009 statement with new guidance on finding, selecting, appraising and synthesizing studies. Includes the 27-item checklist, the abstract checklist and revised flow diagrams.', 'Page MJ, McKenzie JE, Bossuyt PM, et al. BMJ 2021;372:n71');
$r('prisma_checklist', 'PRISMA 2020 checklist', 'https://www.prisma-statement.org/prisma-2020-checklist', 'standard', 'Items and sub-items to report, plus an expanded checklist with recommendations for each.');
$r('prisma_flow', 'PRISMA 2020 flow diagram', 'https://www.prisma-statement.org/prisma-2020-flow-diagram', 'standard', 'Maps records identified, included and excluded at each phase, with reasons for exclusion. Templates for new and updated reviews.');
$r('sr_ma_article', 'Systematic reviews and meta-analyses', 'http://www.ncbi.nlm.nih.gov/pmc/articles/PMC3024725/', 'article', 'An article that breaks down both methods.');
$r('ex_sedentary', 'Systematic review of sedentary behaviour and health indicators in the early years (aged 0–4)', PROXY . 'http://search.ebscohost.com/login.aspx?direct=true&db=s3h&AN=77508160&site=eds-live&scope=site&profile=eds-main', 'article', 'Example of a systematic review.', '', ['Example']);
$r('ex_overweight', 'Primary prevention of overweight in children and adolescents: a meta-analysis', PROXY . 'http://search.ebscohost.com/login.aspx?direct=true&db=a9h&AN=82463896&site=eds-live&scope=site&profile=eds-main', 'article', 'Full-text example of a meta-analysis.', '', ['Example']);
$r('ebling', 'Ebling Library: Systematic Reviews guide', 'https://researchguides.library.wisc.edu/systematic-reviews', 'guide', '', 'University of Wisconsin–Madison');
$r('cornell', 'Cornell University Library: Guide to Evidence Synthesis', 'https://guides.library.cornell.edu/evidence-synthesis', 'guide', '', 'Cornell University');

// Review types.
$r('ex_narrative', "New graduate nurses' readiness to practise", 'https://www.sciencedirect.com/science/article/pii/S2452301120300596', 'article', '', 'AlMekkawi & El Khalil, 2020', ['Narrative']);
$r('ex_systematized', 'Neighbourhood built characteristics and walking among Canadian adults', 'https://pubmed.ncbi.nlm.nih.gov/30652838/', 'article', '', 'Farkas et al., 2019', ['Systematized']);
$r('ex_rapid', "Framing gay men's sexual relationships, 2011–2018", 'https://psycnet.apa.org/record/2020-29968-011', 'article', '', 'Lemay et al., 2020', ['Rapid']);
$r('ex_scoping', 'Personalization in real-time physical activity coaching using mobile apps', 'https://pubmed.ncbi.nlm.nih.gov/31751254/', 'article', '', 'Monteiro-Guerra et al., 2020', ['Scoping']);
$r('ex_umbrella', 'Coffee drinking and cancer risk', 'https://pubmed.ncbi.nlm.nih.gov/32024485/', 'article', '', 'Zhao et al., 2020', ['Umbrella']);
$r('ex_systematic', 'Breastfeeding in the community: how can partners and fathers help?', 'https://pubmed.ncbi.nlm.nih.gov/31936302/', 'article', '', 'Ogbo et al., 2020', ['Systematic']);
$r('ex_meta', 'The effect of yoga on sleep quality and insomnia in women', 'https://pubmed.ncbi.nlm.nih.gov/32357858/', 'article', '', 'Wang et al., 2020', ['Meta-analysis']);
$r('prisma_scr', 'PRISMA for Scoping Reviews (PRISMA-ScR)', 'https://www.prisma-statement.org/scoping', 'standard', 'Published in 2018: 20 essential reporting items and 2 optional ones.');
$r('scr_checklist', 'Scoping review checklist', 'https://guides.lib.lsu.edu/ld.php?content_id=75708190', 'pdf', 'From Tricco et al. (2018), Annals of Internal Medicine 169(7). Free full text at acpjournals.org.', 'Tricco et al., 2018');
$r('scr_webinar', 'Scoping reviews: what they are and how you can do them', 'https://training.cochrane.org/resource/scoping-reviews-what-they-are-and-how-you-can-do-them', 'course', 'Cochrane Learning Live, two videos: methods from 494 scoping reviews, and a worked example.', '', ['Webinar']);
$r('scr_ex1', 'Telehealth nutrition and physical activity interventions for rural adults', 'https://ijbnpa.biomedcentral.com/articles/10.1186/s12966-023-01505-2', 'article', '', 'Herbert et al., 2023', ['Example']);
$r('scr_ex2', 'Nutrition interventions addressing structural racism', 'https://www.cambridge.org/core/journals/nutrition-research-reviews/article/nutrition-interventions-addressing-structural-racism-a-scoping-review/34F82F3590B0C5763786A813ABE26591', 'article', '', 'Greene et al., 2023', ['Example']);
$r('scr_ex3', 'Healthy community design, anti-displacement, and equity strategies in the USA', 'https://pubmed.ncbi.nlm.nih.gov/36580236/', 'article', '', 'Serrano et al., 2023', ['Example']);
$r('ma_eight', 'How to conduct a meta-analysis in eight steps', 'https://link.springer.com/article/10.1007/s11301-021-00247-4', 'article', '', 'Hansen, Steinmetz & Block, 2022');
$r('cochrane_ch10', 'Cochrane Handbook, chapter 10: Analysing data and undertaking meta-analyses', 'https://www.cochrane.org/authors/handbooks-and-manuals/handbook/current/chapter-10', 'guide');
$r('ma_r_book', 'Doing Meta-Analysis with R', 'https://bookdown.org/MathiasHarrer/Doing_Meta_Analysis_in_R/', 'book', 'Starts with R setup and basic data handling, with a companion R package.', 'Harrer et al., 2021', ['Free online']);
$r('ma_r_tutorial', 'How to perform a meta-analysis with R: a practical tutorial', 'https://mentalhealth.bmj.com/content/22/4/153', 'article', 'Worked example from mental health research.');
$r('ma_r_vignette', 'meta package tutorial vignette', 'https://cran.r-project.org/web/packages/meta/vignettes/meta-tutorial.pdf', 'pdf', 'Current R commands for the tutorial above.');
$r('ma_r_bloggers', 'Meta-analysis in R', 'https://www.r-bloggers.com/2021/08/meta-analysis-in-r/', 'website', '', 'R-bloggers');
$r('bk_intro_ma', 'Introduction to Meta-Analysis', 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:$002f$002fSD_LSU$002f0$002fSD_LSU:4161627/ada?qu=SUBJECT%3D%22Meta-analysis.%22', 'book', '', 'Borenstein, Hedges, Higgins & Rothstein, 2009', ['Online']);
$r('bk_pubbias', 'Publication Bias in Meta-Analysis', 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:$002f$002fSD_LSU$002f0$002fSD_LSU:4161775/ada?qu=meta-analysis&rt=false%7C%7C%7CSUBJECT%7C%7C%7CSubject', 'book', '', 'Rothstein, Borenstein & Sutton, 2005', ['Online']);
$r('bk_khan', 'Meta-Analysis: Methods for Health and Experimental Studies', 'https://research.ebsco.com/linkprocessor/plink?id=ee44f222-d1c4-3cdd-bb64-ffb8a839c3fd', 'book', 'Step by step with MetaXL, a free Excel add-on.', 'Khan, 2020', ['Online']);
$r('cat_ma', 'All books on meta-analysis', 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/results?qu=SUBJECT%3D%22meta-analysis%22&te=', 'search', '', 'LSU Libraries catalog');
$r('dl_flow_pdf', 'Which review is right for you? Flowchart and guide', 'https://guides.lib.lsu.edu/ld.php?content_id=57265536', 'pdf', '', '', ['PDF']);
$r('dl_flow_word', 'Which review is right for you? Flowchart and review types', 'https://guides.lib.lsu.edu/ld.php?content_id=83726617', 'word', '', '', ['Word']);
$r('dl_flow_pres', 'Which review is right for you? Presentation, Nov 8, 2024', 'https://guides.lib.lsu.edu/ld.php?content_id=78954505', 'pdf', '', '', ['PDF']);
$r('typology', 'A typology of reviews: an analysis of 14 review types and associated methodologies', 'https://onlinelibrary.wiley.com/doi/epdf/10.1111/j.1471-1842.2009.00848.x', 'article', '', 'Grant & Booth, 2009');
$r('one_author', 'If there is only one author or only one database was searched, a study should not be called a systematic review', 'https://guides.lib.lsu.edu/ld.php?content_id=57322351', 'article');
$r('team_influence', 'The influence of the team in conducting a systematic review', 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC5540536/pdf/13643_2017_Article_548.pdf', 'pdf');
$r('mass_production', 'The mass production of redundant, misleading, and conflicted systematic reviews and meta-analyses', 'https://www.milbank.org/quarterly/articles/mass-production-redundant-misleading-conflicted-systematic-reviews-meta-analyses/', 'article', '', 'Milbank Quarterly');

// Steps.
$r('dl_prisma_checklist', 'PRISMA 2020 checklist (download)', 'https://guides.lib.lsu.edu/ld.php?content_id=57621658', 'download', 'Everything your systematic review should include.');
$r('dl_prisma_flow', 'PRISMA 2020 flow diagram template', 'https://guides.lib.lsu.edu/ld.php?content_id=75707160', 'download', 'For new reviews that searched databases and registers only.');

// Published literature.
$r('iom_31', 'IOM Standards for Systematic Reviews, Standard 3.1', 'https://www.nap.edu/read/13059/chapter/5', 'standard', 'How to run a comprehensive systematic search. From Finding What Works in Health Care (2011), chapter 3.');
$r('prisma_s', 'PRISMA-S: search reporting extension', 'https://osf.io/ygn9w/overview', 'standard', 'An extension to PRISMA for reporting literature searches.');
$r('db_ag', 'Agriculture databases', 'https://guides.lib.lsu.edu/az.php?s=50474', 'database');
$r('db_agext', 'Agricultural Extension, Education & Evaluation databases', 'https://guides.lib.lsu.edu/az.php?s=66663', 'database');
$r('db_econ', 'Economics databases', 'https://guides.lib.lsu.edu/az.php?s=66407', 'database');
$r('db_edu', 'Education databases', 'https://guides.lib.lsu.edu/az.php?s=50970', 'database');
$r('db_nfs', 'Nutrition & Food Sciences databases', 'https://guides.lib.lsu.edu/az.php?s=50922', 'database');
$r('db_psych', 'Psychology databases', 'https://guides.lib.lsu.edu/az.php?s=50585', 'database');
$r('specialists', 'Find your subject specialist', 'https://www.lib.lsu.edu/about/staff/specialists', 'person');
$r('il_tutorial', 'Information literacy tutorial: Identifying & locating sources', 'https://guides.lib.lsu.edu/c.php?g=351700&p=5079764', 'course', 'Five lessons and a quiz on choosing databases and building, narrowing and broadening a search.', '', ['30 min']);
$r('boolean', 'Boolean operators guide', 'https://guides.lib.lsu.edu/c.php?g=456658&p=3218409', 'guide');
$r('umich_keywords', 'Keywords vs. index terms', 'https://guides.lib.umich.edu/c.php?g=283340&p=2126706#s-lg-box-6479967', 'guide', '', 'University of Michigan Library');
$r('tpl_doc', 'Search strategy documentation template', 'https://guides.lib.lsu.edu/ld.php?content_id=57590221', 'download');
$r('tpl_pubmed', 'Search strategy template (PubMed MEDLINE)', 'https://guides.lib.lsu.edu/ld.php?content_id=57598293', 'download');
$r('syntax_guide', 'Database syntax guide', 'https://epoc.cochrane.org/sites/epoc.cochrane.org/files/public/uploads/Resources-for-authors2017/database_syntax_guide.pdf', 'pdf', '', 'Cochrane EPOC');

// Gray literature.
$r('greylog', 'Grey Lit Search Log', 'https://docs.google.com/spreadsheets/d/1CxPy0xXoWmzz_lIBUmebhrqvQcnkqdj5avEqubvx8oE/edit#gid=0', 'tool', 'A spreadsheet for recording every gray literature search.');
$r('greyreporter', 'Grey Literature Reporter', 'https://www.eshackathon.org/software/grey-lit-reporter.html', 'tool', 'A Chrome extension that records results, site addresses and search strings.', '', ['Free']);
$r('greymatters', 'Grey Matters (CADTH)', 'https://www.cadth.ca/resources/finding-evidence/grey-matters', 'guide', 'Checklist of gray literature sources in medicine.');
$r('cochrane_handbook', 'Cochrane Handbook for Systematic Reviews of Interventions', 'https://training.cochrane.org/handbook', 'guide', 'The official guide to preparing and maintaining Cochrane reviews.');
$r('sfu_grey', 'Searching grey literature', 'https://www.lib.sfu.ca/help/research-assistance/format-type/grey-literature', 'guide', '', 'Simon Fraser University');
$r('duke_grey', 'Grey literature guide', 'https://guides.library.duke.edu/c.php?g=1369174', 'guide', '', 'Duke University Medical Center');
$r('policycommons', 'Policy Commons (open access)', 'https://policycommons.net/modules/open/', 'database', '370,000+ items from 77 organizations, including documents from defunct sites.');
$r('nyam', 'NYAM Grey Literature Report', 'http://www.greylit.org/', 'database', 'Health services and public health archive.');
$r('opengrey', 'OpenGrey', 'http://www.opengrey.eu/', 'database', 'About 700,000 European gray literature references.');
$r('wb_okr', 'World Bank Open Knowledge Repository', 'https://openknowledge.worldbank.org/home', 'database');
$r('who_library', 'WHO Library', 'https://www.who.int/library/', 'database');
$r('who_db', 'WHO Library Database', 'https://kohahq.searo.who.int/', 'database');
$r('crl', 'Center for Research Libraries', 'https://www.crl.edu/', 'database');
$r('lsu_repo', 'LSU Scholarly Repository', 'https://repository.lsu.edu', 'database', 'LSU research, data and many graduate theses. To contribute, email ir@lsu.edu.');
$r('lsu_etd', 'Dissertations & Theses @ LSU', PROXY . 'https://search.proquest.com/pqdtlocal1009151?accountid=12154', 'database');
$r('pqdt', 'ProQuest Dissertations & Theses A&I', PROXY . 'https://search.proquest.com/pqdt/dissertations/fromDatabasesLayer?accountid=12154', 'database', 'About 1.2 million dissertations and theses back to 1861.');
$r('oatd', 'OATD', 'https://oatd.org/', 'database', 'Open-access theses from 1,100+ institutions.');
$r('ndltd', 'NDLTD', 'https://ndltd.org/thesis-resources/', 'database');
$r('ethos', 'EThOS (British Library)', 'https://ethos.bl.uk/', 'database', 'UK doctoral theses. Recovering from a cyber-attack; existing links should still resolve.');
$r('ctgov', 'ClinicalTrials.gov', 'https://clinicaltrials.gov/', 'database');
$r('central', 'Cochrane CENTRAL', 'https://www.cochranelibrary.com/central/about-central', 'database');
$r('ictrp', 'WHO ICTRP', 'http://www.who.int/ictrp/en/', 'database');
$r('euctr', 'EU Clinical Trials Register', 'https://www.clinicaltrialsregister.eu/ctr-search/search', 'database');
$r('anzctr', 'Australia New Zealand Clinical Trials Registry', 'http://www.anzctr.org.au/Default.aspx', 'database');
$r('wiki_registries', 'National trial registries by country', 'https://en.wikipedia.org/wiki/Clinical_trials_registry', 'website', '', 'Wikipedia');
$r('hrweb', 'Health Research Web', 'https://healthresearchwebafrica.org.za/', 'website');
$r('pew', 'Pew Research Center', 'http://www.pewresearch.org', 'website');
$r('policyagendas', 'Policy Agendas Project', 'https://www.comparativeagendas.net/', 'database');
$r('policyarchive', 'Policy Archive', 'https://www.policyarchive.org/', 'database');
$r('thinktanks', 'Think tank search', 'https://guides.library.harvard.edu/hks/think_tank_search', 'search', '', 'Harvard Kennedy School Library');
$r('govinfo', 'GovInfo', 'https://www.govinfo.gov/', 'database');
$r('gpo_monthly', 'GPO Monthly Catalog', 'https://www.govinfo.gov/collection/monthly-catalog', 'database');
$r('congress', 'Congress.gov', 'https://www.congress.gov/', 'database');
$r('crs', 'Congressional Research Service reports', 'https://www.congress.gov/crs-products', 'database');
$r('cq', 'CQ Researcher Plus Archive', PROXY . 'https://cqpress.sagepub.com/cqresearcher/', 'database');
$r('pq_congressional', 'ProQuest Congressional', PROXY . 'https://search.proquest.com/congressional', 'database');
$r('wb_elibrary', 'World Bank eLibrary', 'https://elibrary.worldbank.org/', 'database');

// Methodology.
$r('bk_patole', 'Principles and Practice of Systematic Reviews and Meta-Analysis', PROXY . 'https://link.springer.com/10.1007/978-3-030-71921-0', 'book', 'Searching, bias, heterogeneity, GRADE and PRISMA, with chapters on non-randomized, diagnostic and network meta-analyses.', 'Patole (ed.), 2021', ['Online']);
$r('bk_tod', 'Conducting Systematic Reviews in Sport, Exercise, and Physical Activity', PROXY . 'http://link.springer.com/10.1007/978-3-030-12263-8', 'book', '', 'Tod, 2019', ['Online']);
$r('bk_glasziou', 'Systematic Reviews in Health Care: A Practical Guide', 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/detailnonmodal/ent:$002f$002fSD_LSU$002f0$002fSD_LSU:2089231/ada?qu=SUBJECT%3D%22Systematic+reviews%22&st=PD', 'book', '', 'Glasziou, Irwig, Bain & Colditz, 2001', ['Print']);
$r('cat_sr', 'All books on systematic reviews', 'https://lsu.ent.sirsi.net/client/en_US/lsu/search/results?user_id=WEBSERVER&qu=SUBJECT%3D%22Systematic+reviews%22&rt=&qf=ALL', 'search', '', 'LSU Libraries catalog');
$r('iom_standards', 'IOM Standards for Systematic Reviews', 'https://www.ncbi.nlm.nih.gov/books/NBK209518/', 'standard');
$r('hp_ph', 'Guidelines for reviews of health promotion and public health interventions', 'https://ph.cochrane.org/sites/ph.cochrane.org/files/public/uploads/Guidelines%20HP_PH%20reviews.pdf', 'pdf', 'A supplement to the Cochrane Handbook, not a replacement.');
$r('schema_ph', 'A schema for evaluating evidence on public health interventions', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC1731014/', 'article', '', 'Rychetnik & Frommer, University of Sydney');
$r('crd', 'NHS Centre for Reviews and Dissemination guidance', 'https://www.york.ac.uk/crd/guidance/', 'guide');
$r('community_guide', 'The Community Guide methodology', 'https://www.thecommunityguide.org/about/our-methodology', 'guide');
$r('campbell', 'The Campbell Collaboration', 'https://www.campbellcollaboration.org/research-resources/writing-a-campbell-systematic-review.html', 'guide', 'Crime and justice, education, international development, social welfare.');
$r('eppi', 'EPPI-Centre', 'http://eppi.ioe.ac.uk/cms/Default.aspx?tabid=184', 'website');
$r('cee', 'Collaboration for Environmental Evidence', 'http://www.environmentalevidence.org/', 'website');
$r('swe', 'Guidelines for systematic literature reviews in software engineering', 'https://www.elsevier.com/__data/promis_misc/525444systematicreviewsguide.pdf', 'pdf');
$r('agree', 'AGREE: appraising practice guidelines', 'http://www.agreetrust.org/', 'standard');
$r('prisma', 'PRISMA', 'http://www.prisma-statement.org/', 'standard', 'Minimum reporting items for systematic reviews and meta-analyses.');
$r('prisma_p', 'PRISMA-P', 'https://systematicreviewsjournal.biomedcentral.com/articles/10.1186/2046-4053-4-1', 'standard', 'For protocols.');
$r('prisma_ipd', 'PRISMA-IPD', 'http://jama.jamanetwork.com/article.aspx?articleid=2279718', 'standard', 'For individual participant data.');
$r('prisma_equity', 'PRISMA-Equity', 'https://www.prisma-statement.org/equity', 'standard', 'For equity-focused reviews.');
$r('equator', 'EQUATOR Network', 'https://www.equator-network.org/', 'website', 'Library of reporting guidelines for health research.');

// Managing. The LibGuide text gave no links for these tools; these are
// each tool's home page.
$r('rayyan', 'Rayyan', 'https://www.rayyan.ai/', 'tool', 'Independent screening and coding, with tags and filters.', '', ['Free']);
$r('cadima', 'CADIMA', 'https://www.cadima.info/', 'tool', 'Runs and documents systematic reviews and maps.', '', ['Free']);
$r('colandr', 'Colandr', 'https://www.colandrapp.com/', 'tool', 'Machine learning to find relevant citations and extract data from PDFs.', '', ['Free']);
$r('revman', 'RevMan', 'https://revman.cochrane.org/', 'tool', "Cochrane's tool for data extraction and analysis.", '', ['Free']);
$r('distiller', 'DistillerSR', 'https://www.distillersr.com/', 'tool', 'Manages the whole review with multiple independent reviewers.', '', ['Paid']);
$r('robis', 'ROBIS', 'https://www.bristol.ac.uk/population-health-sciences/projects/robis/', 'standard', 'Assesses risk of bias in systematic reviews themselves.', '', ['Free']);

// Help.
$r('askus', 'Ask a librarian', 'https://askus.lib.lsu.edu/ask', 'person', 'Text, call, email or chat with the library.');

echo 'Resources ready: ' . count($resources) . PHP_EOL;

// Blocks. ------------------------------------------------------------------

$ids = fn (array $keys) => array_map(fn ($k) => ['target_id' => $resources[$k]], $keys);
$html = fn (string $markup) => ['value' => trim($markup), 'format' => $format];

$text = fn (?string $title, string $body, string $style = 'default') => Paragraph::create(array_filter([
  'type' => 'guide_text',
  'field_block_title' => $title,
  'field_block_body' => $html($body),
  'field_text_style' => $style,
], fn ($v) => $v !== NULL));

$list = fn (?string $title, array $keys, string $intro = '') => Paragraph::create(array_filter([
  'type' => 'resource_list',
  'field_block_title' => $title,
  'field_block_intro' => $intro !== '' ? $html($intro) : NULL,
  'field_resources' => $ids($keys),
], fn ($v) => $v !== NULL));

$accordion = function (?string $title, array $items, string $intro = '') use ($ids, $html): Paragraph {
  $children = [];
  foreach ($items as $item) {
    $children[] = Paragraph::create(array_filter([
      'type' => 'accordion_item',
      'field_item_title' => $item[0],
      'field_block_body' => !empty($item[1]) ? $html($item[1]) : NULL,
      'field_resources' => !empty($item[2]) ? $ids($item[2]) : NULL,
    ], fn ($v) => $v !== NULL));
  }
  return Paragraph::create(array_filter([
    'type' => 'guide_accordion',
    'field_block_title' => $title,
    'field_block_intro' => $intro !== '' ? $html($intro) : NULL,
    'field_accordion_items' => $children,
  ], fn ($v) => $v !== NULL));
};

// Staff: guide owner and librarian card.
$owner = NULL;
$owner_field = FieldConfig::loadByName('node', 'modular_page', 'field_guide_owner');
if ($owner_field) {
  $staff_bundles = array_keys($owner_field->getSetting('handler_settings')['target_bundles'] ?? []);
  $match = \Drupal::entityQuery('node')->accessCheck(FALSE)
    ->condition('type', $staff_bundles, 'IN')
    ->condition('title', 'Randa', 'CONTAINS')
    ->condition('title', 'Morgan', 'CONTAINS')
    ->range(0, 1)->execute();
  $owner = $match ? (int) reset($match) : NULL;
}
if (!$owner) {
  $skipped[] = 'Guide owner and Librarian cards: no Staff node titled like "Randa … Morgan" was found.';
}
$card = fn (?string $title) => $owner && \Drupal::entityTypeManager()->getStorage('paragraphs_type')->load('librarian_card')
  ? Paragraph::create(array_filter(['type' => 'librarian_card', 'field_block_title' => $title, 'field_librarian' => ['target_id' => $owner]], fn ($v) => $v !== NULL))
  : NULL;

// Intro video.
$video = NULL;
$video_field = FieldConfig::loadByName('paragraph', 'guide_video', 'field_video');
if ($video_field) {
  $types = array_keys($video_field->getSetting('handler_settings')['target_bundles'] ?? []);
  $youtube = 'https://www.youtube.com/watch?v=MPVXWhvIAJU';
  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  foreach ($types as $type) {
    $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($type);
    $source_field = $media_type?->getSource()->getConfiguration()['source_field'] ?? NULL;
    if (!$source_field) {
      continue;
    }
    $existing = \Drupal::entityQuery('media')->accessCheck(FALSE)
      ->condition('bundle', $type)->condition($source_field, '%MPVXWhvIAJU%', 'LIKE')->range(0, 1)->execute();
    try {
      $media = $existing ? $media_storage->load(reset($existing)) : $media_storage->create([
        'bundle' => $type,
        'name' => 'Introduction to systematic reviews',
        $source_field => $youtube,
        'status' => 1,
      ]);
      if ($media->isNew()) {
        $media->save();
      }
      $video = Paragraph::create(['type' => 'guide_video', 'field_block_title' => 'Video introduction', 'field_video' => ['target_id' => $media->id()]]);
    }
    catch (\Throwable $e) {
      $skipped[] = 'Intro video: could not create the media item (' . $e->getMessage() . ').';
    }
    break;
  }
}
else {
  $skipped[] = 'Intro video: there is no Guide: Video block (no remote video media type).';
}

// Sections. ----------------------------------------------------------------

$sections = [];
$section = function (string $title, array $blocks, string $theme = 'light') use (&$sections): void {
  $values = [
    'type' => 'guide_section',
    'field_section_title' => $title,
    'field_guide_blocks' => array_values(array_filter($blocks)),
  ];
  if (FieldConfig::loadByName('paragraph', 'guide_section', 'field_section_theme')) {
    $values['field_section_theme'] = $theme;
  }
  $sections[] = Paragraph::create($values);
};

$section('Overview', [
  $text('What is a systematic review?', <<<'HTML'
<p>A <strong>systematic review</strong> is a methodical, comprehensive synthesis of the literature on a well-formulated research question. It aims to find and synthesize all of the scholarly research on a topic, published and unpublished, in a way that is unbiased and reproducible. Systematic reviews give evidence for practice and policy, identify gaps in research, and may include a meta-analysis.</p>
<p>They take far longer than a traditional literature review and usually need a research team of several people. Before you start, check that the body of literature warrants a systematic review and be clear about why you are doing one.</p>
<h4>Narrative review</h4>
<p>May be evidence-based, but is not itself evidence. Usually has no systematic search protocol or explicit criteria for selecting and appraising studies, and relies on experts to gather and synthesize findings.</p>
<h4>Meta-analysis</h4>
<p>A subset of systematic reviews that statistically combines data from several selected studies into a single conclusion. More subjects and more diverse samples give it greater statistical power than any single study.</p>
HTML),
  $card('Your librarian'),
  $list('The PRISMA statement', ['prisma2020', 'prisma_checklist', 'prisma_flow'], '<p>PRISMA (Preferred Reporting Items for Systematic reviews and Meta-Analyses) was first published in 2009 and updated in 2020. It is a checklist and a flow diagram, meant to be used with the PRISMA Explanation and Elaboration document.</p>'),
  $list('Examples and further reading', ['sr_ma_article', 'ex_sedentary', 'ex_overweight', 'ebling', 'cornell']),
  $video,
]);

$section('Choosing a review type', [
  $text('Which review is right for you?', <<<'HTML'
<p>Answer these questions in order. The first one that points you to a review type is your answer.</p>
<ol>
<li>Do you want to gather <em>all</em> the evidence on a topic? If no: <strong>narrative review</strong>.</li>
<li>Do you have three or more people on your team? If no: <strong>systematized review</strong>.</li>
<li>Do you have 12 to 18 months? If no: <strong>rapid review</strong>.</li>
<li>Is the topic broad, or do you have several research questions? If yes: <strong>scoping review</strong>.</li>
<li>Do you want to review existing systematic reviews? If yes: <strong>umbrella review</strong>.</li>
<li>Do you have a well-formulated research question? If yes: <strong>systematic review</strong>. If no, rethink the question.</li>
<li>Will you combine results with statistical methods? If yes: add a <strong>meta-analysis</strong>.</li>
</ol>
HTML),
  $text('Types of reviews', <<<'HTML'
<table>
<caption>Review types and what sets each apart</caption>
<thead><tr><th scope="col">Type</th><th scope="col">What it is</th></tr></thead>
<tbody>
<tr><th scope="row">Literature (narrative)</th><td>Broad scope, no standard methods and no set protocol.</td></tr>
<tr><th scope="row">Systematized</th><td>Uses some systematic methods without being a full systematic review. Fits individuals without a team.</td></tr>
<tr><th scope="row">Rapid</th><td>Systematic methods under a time limit. Shortcuts such as fewer search terms raise the risk of bias.</td></tr>
<tr><th scope="row">Scoping / systematic map</th><td>Maps the evidence on a broad topic to find gaps. Usually doesn't synthesize results; can take longer than a systematic review.</td></tr>
<tr><th scope="row">Umbrella</th><td>A review of systematic reviews. Most useful when several competing interventions exist.</td></tr>
<tr><th scope="row">Systematic review</th><td>Comprehensive, unbiased and reproducible synthesis of published and unpublished research on one well-formulated question.</td></tr>
<tr><th scope="row">Meta-analysis</th><td>Statistically combines findings from quantitative studies, on its own or as part of a systematic review.</td></tr>
</tbody>
</table>
HTML),
  $text('Systematic vs. traditional literature review', <<<'HTML'
<table>
<caption>How the two kinds of review differ at each stage</caption>
<thead><tr><th scope="col">Stage</th><th scope="col">Systematic review</th><th scope="col">Traditional literature review</th></tr></thead>
<tbody>
<tr><th scope="row">Question</th><td>One well-defined question; aims to find all existing evidence transparently and reproducibly.</td><td>May be broad, place the author's work in context, or support a viewpoint.</td></tr>
<tr><th scope="row">Searching</th><td>Aims to find all published and unpublished literature and documents the process.</td><td>Often ad hoc, based on what the author knows.</td></tr>
<tr><th scope="row">Selection</th><td>States explicit reasons for including and excluding studies.</td><td>Often gives no reasons.</td></tr>
<tr><th scope="row">Quality</th><td>Assesses risk of bias and quality of evidence.</td><td>Often doesn't consider study quality.</td></tr>
<tr><th scope="row">Synthesis</th><td>Conclusions weigh study quality; may include a meta-analysis.</td><td>More qualitative; may not weigh study quality.</td></tr>
</tbody>
</table>
HTML),
  $text(NULL, '<p><strong>A systematic review needs more than one author.</strong> A team reduces bias and supports inclusion decisions, and many journals reject single-author reviews labeled "systematic."</p>', 'callout'),
  $text('Systematized reviews', <<<'HTML'
<p>A systematized review is what most graduate students set out to write. It uses one or more elements of the systematic review process without claiming to be a full systematic review (Grant &amp; Booth, 2009).</p>
<h4>Strengths</h4>
<ul><li>The search is usually the most systematic part.</li><li>Shows you understand the full process.</li><li>Can lead to a dissertation or a funded project.</li></ul>
<h4>Weaknesses</h4>
<ul><li>Quality assessment and synthesis are often unclear or missing.</li><li>Often based on a small set of articles, so bias is more likely.</li><li>Academic requirements tend to come before method.</li></ul>
HTML),
  $list('Examples by review type', ['ex_narrative', 'ex_systematized', 'ex_rapid', 'ex_scoping', 'ex_umbrella', 'ex_systematic', 'ex_meta']),
  $list('Scoping reviews', ['prisma_scr', 'scr_checklist', 'scr_webinar', 'scr_ex1', 'scr_ex2', 'scr_ex3'], <<<'HTML'
<p>A scoping review synthesizes the available evidence on a topic to show the scope of the literature. Choose one over a systematic review when you want to:</p>
<ul>
<li>identify the types of evidence available in a field</li>
<li>clarify key concepts and definitions</li>
<li>examine how research on a topic is done</li>
<li>identify key characteristics or factors related to a concept</li>
<li>find and analyze knowledge gaps</li>
<li>prepare for a systematic review by testing inclusion criteria and questions</li>
</ul>
<p>Munn et al. (2018), <em>BMC Medical Research Methodology</em> 18:143.</p>
HTML),
  $accordion('Meta-analysis', [
    ['How-to guides', '', ['ma_eight', 'cochrane_ch10']],
    ['Using R', '', ['ma_r_book', 'ma_r_tutorial', 'ma_r_vignette', 'ma_r_bloggers']],
    ['Books in the LSU catalog', '', ['bk_intro_ma', 'bk_pubbias', 'bk_khan', 'cat_ma']],
  ], '<p>A meta-analysis is a quantitative statistical analysis of several separate but similar studies. It should sit inside a systematic review, though not every systematic review includes one. As with any review, work as a team: a solo meta-analysis raises questions about validity and reliability.</p>'),
  $list('Downloads and further reading', ['dl_flow_pdf', 'dl_flow_word', 'dl_flow_pres', 'typology', 'one_author', 'team_influence', 'mass_production']),
]);

$section('How librarians can help', [
  $text('Consultant or co-author', <<<'HTML'
<h4>Consultant (most common)</h4>
<p>Works best for most students. A librarian joins at the points in the review where you need help.</p>
<h4>Co-author (more than a year)</h4>
<p>A larger, hands-on commitment for the whole project, usually longer than a year.</p>
<p>Librarians may decline co-authorship because of workload or availability. Reviews often take a year or more, so contact a librarian early.</p>
HTML),
  $text('Timeline and what a co-author does', <<<'HTML'
<table>
<caption>18 steps, estimated time, and the librarian co-author's role</caption>
<thead><tr><th scope="col">Step</th><th scope="col">Time</th><th scope="col">Librarian co-author</th></tr></thead>
<tbody>
<tr><td>1. Assemble the team and choose a project manager</td><td>Varies</td><td>Guidance</td></tr>
<tr><td>2. Choose a review methodology</td><td>2 weeks</td><td>Guidance</td></tr>
<tr><td>3. Define the research question</td><td>2 weeks</td><td>Explains frameworks such as PICO</td></tr>
<tr><td>4. Define inclusion and exclusion criteria</td><td>1 week</td><td>Guidance</td></tr>
<tr><td>5. Select databases</td><td>1 week</td><td>Suggests databases</td></tr>
<tr><td>6. Select gray literature sources</td><td>1 week</td><td>Suggests sources</td></tr>
<tr><td>7. Write the search for the main database</td><td>1 week</td><td>Leads the search strategy</td></tr>
<tr><td>8. Write and register the protocol</td><td>Varies</td><td>Comments; guides registration</td></tr>
<tr><td>9. Translate the search for each database</td><td>2 weeks</td><td>Translates the strategy</td></tr>
<tr><td>10. Run searches and export to a citation manager</td><td>2 weeks</td><td>Runs and exports searches</td></tr>
<tr><td>11. Remove duplicates</td><td>2–4 weeks</td><td>Deduplicates or trains the team</td></tr>
<tr><td>12. Screen titles and abstracts*</td><td>2–3 months</td><td>Recommends screening software</td></tr>
<tr><td>13. Retrieve full texts*</td><td>1 month</td><td>Trains the team on retrieval</td></tr>
<tr><td>14. Screen full texts*</td><td>2–3 months</td><td>Guidance</td></tr>
<tr><td>15. Assess risk of bias</td><td>2–3 months</td><td>Guidance</td></tr>
<tr><td>16. Extract data</td><td>2–3 months</td><td>Guidance</td></tr>
<tr><td>17. Meta-analysis or synthesis</td><td>2–3 months</td><td>Guidance</td></tr>
<tr><td>18. Write the manuscript</td><td>2–3 months</td><td>Writes the search methods</td></tr>
</tbody>
</table>
<p>* Time depends on how many citations you need to screen. Adapted from the Evidence Synthesis Librarians at Cornell University Library.</p>
HTML),
], 'dark');

$section('Steps in a systematic review', [
  $text('The process', <<<'HTML'
<ol>
<li><strong>Check for existing reviews and protocols.</strong> Is a new review still needed? Should the question change? Are earlier reviews sound, current and broad enough?</li>
<li><strong>Identify your research question.</strong> Build a focused question, define key terms, and use a framework to set the scope.</li>
<li><strong>Define inclusion and exclusion criteria.</strong> Set criteria for populations, study designs, interventions, comparisons and outcomes, and write them into your protocol.</li>
<li><strong>Search for studies.</strong> Search the right databases with a librarian, search gray literature methodically, collect everything in a reference manager, and remove duplicates.</li>
<li><strong>Select studies.</strong> Screen titles and abstracts, then full texts, against your criteria. Use two independent reviewers and resolve disagreements by consensus.</li>
<li><strong>Extract data.</strong> Use a spreadsheet or review software, and pilot the form first to settle which fields you need.</li>
<li><strong>Evaluate the risk of bias.</strong> Use a tool such as Cochrane's RoB tool, adapted to the study designs you include.</li>
<li><strong>Present results and assess the evidence.</strong> Report your search strategies and criteria in enough detail to update later. Run a meta-analysis if the studies allow, then make recommendations or name research gaps.</li>
</ol>
<p>Process adapted from Jessica Kaufman, Cochrane Consumers &amp; Communication Review Group, La Trobe University (2011), CC BY-SA.</p>
HTML),
  $text('The parts of a published review', '<p>An annotated walk through each part of a published systematic review, using an example from the Cochrane Library.</p><p>[Editor: the LibGuide shows this as a diagram. Upload it to Media and add it here with alt text that names each part.]</p>'),
  $text('Finding and appraising existing reviews', '<p>A review that calls itself "systematic" isn\'t necessarily rigorous, reproducible or exhaustive. Appraise it before you rely on it.</p><p>Existing reviews on a related topic are a good place to start: borrow their search strategies and sources as models for your own.</p><p>[Editor: add the two appraisal tools and the libraries of systematic reviews as a Resource list.]</p>'),
  $accordion('Formulating your question', [
    ['PICO and variants (quantitative)', '<p><strong>P</strong>opulation · <strong>I</strong>ntervention or exposure · <strong>C</strong>omparison or control · <strong>O</strong>utcome</p><p>Variants add <strong>S</strong>tudy design (PICOS), <strong>T</strong>imeframe (PICOT) or <strong>C</strong>ontext (PICOC).</p>'],
    ['SPICE (qualitative)', '<p><strong>S</strong>etting · <strong>P</strong>erspective · <strong>I</strong>ntervention · <strong>C</strong>omparison · <strong>E</strong>valuation</p>'],
    ['SPIDER (qualitative and mixed methods)', '<p><strong>S</strong>ample · <strong>P</strong>henomenon of <strong>I</strong>nterest · <strong>D</strong>esign · <strong>E</strong>valuation · <strong>R</strong>esearch type</p>'],
    ['ECLIPSE (health policy and management)', '<p><strong>E</strong>xpectation · <strong>C</strong>lient group · <strong>L</strong>ocation · <strong>I</strong>mpact · <strong>P</strong>rofessionals · <strong>SE</strong>rvice</p>'],
    ['Example PICO questions', '<ul><li>In school-age children, does a school physical activity program, compared with none, reduce childhood obesity?</li><li>Are adults with a smoking history more likely to develop esophageal cancer than adults who never smoked?</li><li>In women of reproductive age, do oral contraceptives, compared with IUDs, raise the risk of blood clots?</li><li>In adults with hypertension, does home blood pressure monitoring improve medication adherence?</li></ul><p>Templates adapted from Southern Illinois University Edwardsville. Source: Cochrane Handbook v6.0 (2019), chapter 2.</p>'],
  ], '<p>A good question is answerable and fills a real gap. It can be broad or narrow, and it can be about interventions, diagnosis, screening, experiences, management strategies or policy.</p><p>A framework is scaffolding for your question. The question matters more than which framework you pick. PICO fits most questions about effectiveness; the others suit different kinds of questions.</p>'),
  $text('Inclusion and exclusion criteria', '<p>Set your criteria after the question is final and before you search. They define the scope of the synthesis and usually go in the methods section; define any terms readers may not know.</p><p><strong>Language.</strong> Research is published worldwide. Limiting a review to English can introduce bias.</p><p><strong>Empty reviews.</strong> Sometimes no studies meet the criteria. Empty reviews are more vulnerable to publication bias, but they still show where the gaps are.</p>'),
  $text('Creating a protocol', '<p>The protocol is your review\'s roadmap. Write it before you search. It can change during the review: after you submit it, file an amendment for any change.</p><p>Register the protocol before you start. Registration makes the review transparent and reproducible, and stops other teams duplicating your work. If you work with Cochrane or the Campbell Collaboration, publish the protocol with them.</p><p>Your methods section should read as a narrative and include at least one complete search string, copied and pasted, with the full strings in an appendix.</p><p>[Editor: add the required protocol elements and the independent registries.]</p>'),
  $list('Results and the PRISMA flow diagram', ['dl_prisma_checklist', 'dl_prisma_flow'], '<p>Your results section reports how many records came from each source, how many duplicates you removed, and the PRISMA flow diagram showing records identified, included and excluded, with reasons.</p>'),
]);

$section('Searching the published literature', [
  $list('Search standards', ['iom_31', 'prisma_s']),
  $text('Search strategy checklist', '<ul><li>Include every concept in the strategy.</li><li>Use all appropriate subject headings, and explode them where appropriate.</li><li>Use subheadings and floating subheadings correctly.</li><li>Combine keywords with controlled vocabulary, and add synonyms and acronyms.</li><li>Use truncation and spelling variants.</li><li>Apply limits (language, years, publication type) deliberately.</li><li>Check your Boolean operators, and that combined line numbers point to the right searches.</li><li>Check how known relevant articles are indexed.</li><li>Adapt the strategy for each database.</li></ul>'),
  $text('Documenting the search', '<table><caption>Record these for every search you run</caption><thead><tr><th scope="col">Record</th><th scope="col">Example or why</th></tr></thead><tbody><tr><th scope="row">Database name</th><td>Academic Search Complete, Medline, Web of Science</td></tr><tr><th scope="row">Database host</th><td>EBSCOhost, ProQuest, Wiley</td></tr><tr><th scope="row">Date searched</th><td>Results change as new articles are added</td></tr><tr><th scope="row">Years covered</th><td>Check each journal\'s coverage in the catalog</td></tr><tr><th scope="row">Search terms</th><td>They may differ by database</td></tr><tr><th scope="row">Language limits</th><td>And which databases they applied to</td></tr><tr><th scope="row">The full search</th><td>Copy and paste it exactly</td></tr></tbody></table>'),
  $list('Choosing databases', ['db_ag', 'db_agext', 'db_econ', 'db_edu', 'db_nfs', 'db_psych', 'specialists', 'il_tutorial'], '<p>Find five key papers and see where they are indexed. Search at least one large multidisciplinary database and two smaller subject databases. Teams across universities should confirm everyone has the same database access.</p>'),
  $accordion('Search tips', [
    ['Boolean operators', '<p><strong>AND</strong> combines different concepts and narrows results. <strong>OR</strong> joins synonyms and broadens them. <strong>NOT</strong> excludes a term. Parentheses group terms.</p><p><code>"social media" AND (teen* OR adolescent*) NOT bullying*</code></p>', ['boolean']],
    ['Truncation and wildcards', '<p>Truncation (<code>*</code>) finds word endings. Cut at a meaningful stem: <code>carbohydrat*</code>, not <code>car*</code>.</p><p>Wildcards stand in for spelling variants, often <code>?</code> or <code>$</code> depending on the database: <code>behavio$r</code>.</p>'],
    ['Subject headings and thesauri', '<p>Most databases assign controlled vocabulary. MeSH (Medline), PsycInfo and SocINDEX have especially rich thesauri. Terms differ between databases, so look them up in each one.</p>'],
    ['Keywords vs. index terms', '<p>Use both. Keywords broaden results and search titles and abstracts. Index terms focus results on what indexers assigned.</p>', ['umich_keywords']],
    ['Translating a search between databases', '<p>Each database has its own syntax. <code>vitamin D[tiab]</code> works in PubMed but not in Web of Science.</p>', ['tpl_doc', 'tpl_pubmed', 'syntax_guide']],
  ]),
]);

$section('Searching the gray literature', [
  $text('What is gray literature?', '<p>Cochrane defines gray literature as material not formally published in books or journals: government reports, conference proceedings, dissertations, unpublished trials. At a minimum, search theses and dissertations.</p>'),
  $text(NULL, '<p><strong>Google Scholar isn\'t built for systematic reviews.</strong> Its filters are weak, there\'s no true advanced search, and its ranking is opaque. For gray literature, run a simple search, review about the first 300 results, and watch for predatory journals.</p>', 'callout'),
  $list('Googling the greys', ['greylog', 'greyreporter'], '<p>Don\'t rely on Google alone: Bing and other engines surface different results.</p><ol><li>Limit to a site type: add <code>site:.org</code> or <code>site:.gov</code>.</li><li>Limit to a file type: add <code>filetype:pdf</code> or <code>filetype:doc</code>.</li><li>Try other countries\' Google, such as Google Australia or Google UK.</li><li>Try DuckDuckGo, which doesn\'t personalize results.</li><li>Log every search.</li></ol>'),
  $accordion('Gray literature sources', [
    ['Guides and manuals', '', ['greymatters', 'cochrane_handbook', 'sfu_grey', 'duke_grey']],
    ['Databases and repositories', '', ['policycommons', 'nyam', 'opengrey', 'wb_okr', 'who_library', 'who_db', 'crl']],
    ['Theses and dissertations', '', ['lsu_repo', 'lsu_etd', 'pqdt', 'oatd', 'ndltd', 'ethos']],
    ['Clinical trial registries', '', ['ctgov', 'central', 'ictrp', 'euctr', 'anzctr', 'wiki_registries', 'hrweb']],
    ['Public policy', '', ['pew', 'policyagendas', 'policyarchive', 'thinktanks']],
    ['Government documents', '', ['govinfo', 'gpo_monthly', 'congress', 'crs', 'cq', 'pq_congressional', 'wb_elibrary']],
  ]),
]);

$section('Methodology and documentation', [
  $list('Books in the LSU catalog', ['bk_patole', 'bk_tod', 'bk_glasziou', 'cat_sr']),
  $accordion('Guidelines by discipline', [
    ['Health and medicine', '', ['cochrane_handbook', 'iom_standards', 'hp_ph']],
    ['Public health methods', '', ['schema_ph', 'crd', 'community_guide']],
    ['Social sciences', '', ['campbell', 'eppi']],
    ['Environment, software engineering and others', '', ['cee', 'swe', 'agree']],
  ]),
  $list('Reporting standards', ['prisma', 'prisma_p', 'prisma_ipd', 'prisma_equity', 'equator'], '<p>Use a reporting standard to shape your protocol and manuscript. Many journals require one.</p>'),
]);

$section('Managing the process', [
  $text('When to stop searching', '<p>Knowing when to stop is the hardest part. Set an end date, or stop when the same articles keep coming back. Ask yourself:</p><ul><li>Have I searched every appropriate database?</li><li>Have I searched non-English sources and gray literature?</li><li>Have I refined my terms from the most relevant citations?</li><li>How many new, relevant citations did my last two searches find?</li></ul><h4>After the search</h4><ol><li>Screen by title and abstract.</li><li>Select by full text.</li><li>Appraise the methods.</li><li>Code the same information from each study.</li><li>Explain and discuss the results.</li><li>Summarize in a report.</li></ol>'),
  $list('Screening and review tools', ['rayyan', 'cadima', 'colandr', 'revman', 'distiller', 'robis'], '<p>[Editor: the LibGuide also lists the PIECES workbook, an Excel approach to screening and data extraction from a Texas A&amp;M librarian. Add it as a Resource once you have its link.]</p>'),
  $accordion('Citation management', [
    ['Export, import, repeat', '<ol><li>Translate your search into each database\'s syntax and run it.</li><li>Export the results as RIS, BibTeX, MEDLINE or XML and save the files.</li><li>Import the files into your citation manager.</li></ol>'],
    ['PubMed to EndNote', '<ol><li>In PubMed, choose <strong>Send to</strong> &gt; <strong>File</strong>, format <strong>MEDLINE</strong>, and create the file.</li><li>In EndNote, choose <strong>File</strong> &gt; <strong>Import</strong> and pick the <strong>PubMed (NLM)</strong> import option.</li></ol>'],
    ['Removing duplicates', '<ul><li><strong>EndNote:</strong> References &gt; Find Duplicates, then Keep This Record. See Bramer et al. (2016), <em>JMLA</em> 104(3) for a thorough method.</li><li><strong>Zotero:</strong> open Duplicate Items and merge.</li><li><strong>Mendeley:</strong> Tools &gt; Check for Duplicates, choose what to keep, and merge.</li><li><strong>Covidence:</strong> removes duplicates automatically.</li></ul>'],
  ]),
  $text('Academic integrity', '<p>The <strong>LSU Academic Integrity Overview</strong> is a self-enroll Moodle module from the Office of Student Advocacy &amp; Accountability. It covers avoiding misconduct and gives you a certificate when you finish. <strong>Using Information Ethically</strong> covers plagiarism and how to cite quoted, paraphrased and summarized sources.</p>'),
]);

$section('Systematic review trainings', [
  $text('Workshops and recordings', '<p>Workshops and recorded trainings on planning, searching and screening a systematic review.</p><p>[Editor: this LibGuide page could not be read. Add the trainings as a Resource list, or add an Events carousel section for systematic review workshops.]</p>'),
]);

$section('Get help', [
  $list('Ask us', ['askus']),
  $card(NULL),
  $text('More resources', '<ul><li><strong>Juno Evidence Synthesis Directory:</strong> curated guidance, standards, courses and software for evidence synthesis in agriculture, food security and climate science.</li><li><strong>Practical guidance for food-related systematic reviews:</strong> four practices for better food reviews: comprehensive searching, clear reporting, standard methods and the right tools.</li><li><strong>Curly quotes and phrase searching:</strong> many search platforms mishandle curly quotation marks, so type straight quotes in your search strings.</li><li><strong>PIECES web conference series:</strong> a completed series from the NNLM South Central Region.</li></ul><p>[Editor: these had no links in the LibGuide. Turn them into Resources once you have the URLs.]</p>'),
]);

// The page. ----------------------------------------------------------------

$values = [
  'type' => 'modular_page',
  'title' => 'Systematic Reviews',
  'status' => 0,
  'field_scroll_section' => $sections,
  'field_guide_source' => ['uri' => $source_url],
];
$node_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'modular_page');
if (isset($node_fields['field_modular_type'])) {
  $values['field_modular_type'] = 'guide';
}
if (isset($node_fields['field_intro'])) {
  $values['field_intro'] = $html('<p>How to plan, search, document and report a systematic review, from choosing the right review type to drawing your PRISMA flow diagram.</p>');
}
if ($owner && isset($node_fields['field_guide_owner'])) {
  $values['field_guide_owner'] = ['target_id' => $owner];
}
if (isset($node_fields['field_guide_subjects'])) {
  $vocabs = array_keys($node_fields['field_guide_subjects']->getSetting('handler_settings')['target_bundles'] ?? []);
  $names = ['Agriculture', 'Civil & Environmental Engineering', 'Coast & Environment', 'Engineering', 'Geology & Geophysics', 'Kinesiology', 'Nutrition & Food Sciences', 'Science'];
  $terms = $vocabs ? \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
    ->condition('vid', $vocabs, 'IN')->condition('name', $names, 'IN')->execute() : [];
  $values['field_guide_subjects'] = array_map(fn ($tid) => ['target_id' => $tid], array_values($terms));
  if (count($terms) < count($names)) {
    $skipped[] = 'Subjects: found ' . count($terms) . ' of ' . count($names) . ' (' . implode(', ', $names) . ').';
  }
}
if (!empty($args['path'])) {
  $values['path'] = ['alias' => '/' . ltrim($args['path'], '/')];
}

$page = Node::create($values);
$page->setRevisionLogMessage('Demo: Systematic Reviews LibGuide rebuilt as a guide page (palefire_guide_demo.php).');
$page->save();

echo 'Created unpublished guide page: node/' . $page->id() . ' (' . $page->toUrl('canonical', ['absolute' => TRUE])->toString() . ')' . PHP_EOL;
foreach ($skipped as $line) {
  echo 'Skipped: ' . $line . PHP_EOL;
}
echo 'Review it, then publish. Run: drush cr' . PHP_EOL;
