<?php
if (!defined('MEDIAWIKI')) die();

require_once( "$IP/includes/SpecialPage.php" );

// Interface to mediawiki
$wgBib2SMWCredits =
  array(
	'name' => 'Bib2SMW',
	'version' => '0.1',
	'author' => 'Olaf Lenz, Christopher Wagner, David Schwörer',
	//	'url' => 'https://github.com/olenz/externbib',
	'description' => 'Load BibTeX entries into SMW Subobjects',
	'descriptionmsg' => 'bib2smw-desc',
	);
$wgExtensionCredits['parserhook'][] = $wgBib2SMWCredits;
$wgExtensionCredits['specialpage'][] = $wgBib2SMWCredits;

$dir = dirname(__FILE__) . '/';

// directly load Bib2SMW.class.php, as an instance will be created
// anyway
require_once($dir . 'Bib2SMW.class.php');

//$wgAutoloadClasses[ 'SpecialBib2SMW' ] = $dir . '/SpecialBib2SMW.php';
$wgExtensionMessagesFiles['Bib2SMW'] = $dir . 'Bib2SMW.i18n.php';
$wgExtensionFunctions[] = 'efBib2SMWSetup';


// defaults
if (!isset($wgBibTeXPDFPath) || $wgBibTeXPDFPath == '' )
  $wgBibTeXPDFPath = $dir.'pdf/';
if (!isset($wgBibTeXXMLPath) || $wgBibTexXMLPath == '' )
  $wgBibTeXXMLPath = $dir.'work/';
if (!isset($wgBibTeXDBPage) || $wgBibTeXDBPage == '' ) 
  $wgBibTeXDBPage = "Database";
if (!isset($wgBibTeXDBSize))
  $wgBibTeXDBSize = 100000;
			   


// setup the module
function efBib2SMWSetup() {
  global $wgParser, 
    $wgBib2SMW,
    $wgBib2SMWDBPage;

  $wgBib2SMW = new Bib2SMW();

  // register the tags
  $wgParser->setHook("updateBib2SMW", array($wgBib2SMW, 'updateDB'));
  
  return true;
}

?>
