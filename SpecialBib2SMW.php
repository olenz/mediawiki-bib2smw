<?php
class SpecialBib2SMW extends SpecialPage {
  function __construct() {
    parent::__construct( 'Bib2SMW' );
  }
 
  function execute( $par ) {
    $request = $this->getRequest();
    $output = $this->getOutput();
    $this->setHeaders();
 
    # Get request data from, e.g.
      $param = $request->getText( 'param' );
    
    # Do stuff
    # ...
    
    $wikitext = 'Hello world!';
    $output->addWikiText( $wikitext );

    $namespace = NS_MAIN;
    $prefixList = $this->getNamespaceKeyAndText($namespace, $wgBibTeXDBPage);
    list( $namespace, $prefixKey, $prefix ) = $prefixList;

    $dbr = wfGetDB( DB_SLAVE );

    $conds = array(
		   'page_title' . $dbr->buildLike( $prefixKey, $dbr->anyString() ),
		   //'page_title >= ' . $dbr->addQuotes( $fromKey ),
		   );
    
    $hideredirects=true;
    if ( $hideredirects ) {
      $conds['page_is_redirect'] = 0;
    }

    $res = $dbr->select( 'page',
			 array( 'page_namespace', 'page_title', 'page_is_redirect' ),
			 $conds,
			 __METHOD__,
			 array(
			       'ORDER BY'  => 'page_title',
			       'LIMIT'     => $this->maxPerPage + 1,
			       'USE INDEX' => 'name_title',
			       )
			 );

  }
}