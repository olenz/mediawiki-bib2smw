<?php
if (!defined('MEDIAWIKI')) die();

class BibTex {
  // The Page which has all the entrys
  var $database;
  
  // parameters
  var $dbpage;

  // old from ExternBib

  // the database
  var $dbs = array();
  // the currently handled entry
  var $current_entry;


  var $filedirs;
  var $filebaseurls;
  var $doibaseurl;
  var $eprintbaseurl;
  var $default_format;

  function BibTex($wgBibTexDBPage,
		     $doibaseurl,
		     $eprintbaseurl,
		     $default_format) {
    //$dbpage = $wgBibTexDBPage;
    //$database = Title::newFromText($dbpage);
    //if ($database==NULL || !$database->exists()){
    //  echo "Warning:\"$dbpage\" does not exist!<br>";
    //}
    //else {
    //  $out .= "page is:".$page."<br>";
    //}
  }
  
  function updateDB( $input, $argv, $parser, $frame ){
    global $wgBibTeXXMLPath;
    global $wgBibTeXDBPage;
    global $wgBibTeXDBSize;
    $nocheck=false;
    $len=strlen($wgBibTeXDBPage);
    $match=true;
    $error='';
    //$title=$_GET['title'];
    $title=$parser->mTitle->mTextform;
    if ( ! substr($title,1,$len-1) === substr($wgBibTeXDBPage,1,-1)){
      $error.="Not called from a valid page"; return $error;
    }
    if ( strncmp($title,$wgBibTeXDBPage,1) != 0){
      $error.="Not called from a valid page"; return $error;
    }
    $dbid=substr($title,$len);
    if ($dbid==''){
      $error.="Not called from a valid page"; return $error;
    }
    $dbid=(int) $dbid;
    $ins=explode(',',$input);
    $from=$ins[0];
    if (isset($ins[1])){
      $step=$ins[1];
    }
    else{
      $step=$wgBibTeXDBSize;
    }
    if ($from == -1){
      $from=$dbid*$step;
    }
    if ( !isset($_SERVER['SERVER_ADDR']) ||$_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR'] || (isset($_GET['enforce'] ) && $_GET['enforce']=='updateDB') || $nocheck){
      //print_r($GLOBALS);
      //die();
      $page=new WikiPage(Title::newFromText($title));
      $page->clear();
      $to=$from+$step;
      $GLOBALS['wgLangConvMemc']->expireAll();
      return $this->doUpdateDB($parser, $wgBibTeXXMLPath, $from, $to,$error);
    }
    else{
      $error.="You have no right to update the db. If you think you have the right, see in the code how to override this check.";
      //echo "Replaced ".$this->recursive_array_replace("lastExpireAll",$GLOBALS,0)." times!";
      //print_R($GLOBALS);
      //die();
      return $error;
    }
  }
  
  function doUpdateDB ($parser, $xmlPath, $from, $to, $error){
    $xml=simplexml_load_file($xmlPath);
    $k=0;
    $parser->disableCache();
    //foreach ($xml->entry as $entry){
    while($xml->entry[$k]){
      $entry=$xml->entry[$k];
      $k+=1;
      if ($k >= $from && $k < $to){
	$cur_id='';
	$data=array();
	foreach($entry->attributes() as $a => $b){
	  if ($a == "id"){
	    $cur_id = "$b";
	  }
	}
	if ($cur_id==''){
	  $error.='The Element $k seems to have no id. skipp it.<br>';
	}
	else{
	  array_push($data,"BibTeX_id=$cur_id");
	  foreach($entry as $t1){
	    foreach($t1 as $a => $b){
	      $b=str_replace(array('[',']'),array('&#91;','&#93;'),$b);
	      $b=str_replace(array('{','}'),array('',''),$b);
	      if ($a=="year"){
		$c=(int)substr($b,-4);
		if ($c < 100){
		  if ($c > 20)
		    $c+=1900;
		  else
		    $c+=2000;
		}
		if ( "$c" !== "$b" ){
		  $error.="$b was changed to $c<br>";
		}
		array_push($data,"BibTeX_year_int=$c");
	      }
	      array_push($data,"BibTeX_$a=$b");
	    }
	  }
	  $subobjectname=$cur_id;
	  array_unshift($data,$cur_id);
	  array_unshift($data,$parser);
	  // As of PHP 5.3.1, call_user_func_array() requires that
	  // the function params be references. Workaround via
	  // http://stackoverflow.com/questions/2045875/pass-by-reference-problem-with-php-5-3-1
	  $refParams = array();
	  foreach ( $data as $key => $value ) {
	    $refParams[$key] = &$data[$key];
	  }
	  
	  //$error.=$cur_id.' &nbsp; &nbsp; &nbsp; ';
	  $err = call_user_func_array(array('SMWSubobject','render'),$refParams);
	  //$err = call_user_func_array(array('SMWSubobject','render'),$data);
	  if ($err){
	    echo "Error occured<br>\n";
	    array_shift($data);
	    print_r($data);
	    echo "\$cur_id=$cur_id<br>\n";
	    echo $err;
	  }
	}
      }
    }
    return $error."Number of entrys: $k, we have done $from to ".max(min($k,$to),$from);
  }

  function recursive_array_replace($needle,$haystack,$replace) {
    $replaced=0;
    foreach($haystack as $key=>$value) {
      if($needle===$key) {
	$value=$replace;
	$replaced+=1;
      }
      elseif ($value !== $haystack){
	$replaced+=recursive_array_replace($needle,$value,$replace);
      }
    }
    return $replaced;
  }

  //////////////////////////////////////////////////
  // Handle <bibentry>
  //////////////////////////////////////////////////
  // bibentry creates an unsorted list of all bib entries provided in
  // the tag
  // Example: <bibentry>lenz07b,holm98a</bibentry>
  function bibentry( $input, $argv, $parser, $frame ) {
    global $wgOut, $wgParser;

    // TODO: check whether this can be avoided
    // disable the cache
    $parser->disableCache();

    // parse $input and split it into entries
    $input = trim($input);
    $entries = preg_split("/[\s,]+/", $input);

    // start writing into the output buffer
    ob_start();
    
    echo "<ul class=\"plainlinks\">\n";
    $this->format_entries($entries, $argv);
    echo "</ul>\n";
  
    // get everything from the output buffer
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }

  //////////////////////////////////////////////////
  // Handle <bibsearch>
  //////////////////////////////////////////////////
  // bibsearch creates an unsorted list of all bib entries provided by the query between the tags
  // Example: <bibsearch>author=holm</bibsearch>
  function bibsearch( $input, $argv, $parser, $frame ) {
    // TODO: check whether this can be avoided
    // disable the cache
    $parser->disableCache();
    
    // start writing into the output buffer
    ob_start();

    // search for entries, using the input as query
    $found_entries = $this->search_entries($input);

    if ($found_entries) {
      // Output the results
      $this->format_entries($found_entries, $argv);
    }
  
    // get everything from the output buffer
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }


  //////////////////////////////////////////////////
  // helper functions
  //////////////////////////////////////////////////
  // returns whether $key exists in the array and whether it is true
  function array_isset($key, $array, $defaults) {
    return 
      (array_key_exists($key, $array) 
       && ($array[$key]==true 
	   || $array[$key]=="yes" 
	   || $array[$key]==1))
      || (array_key_exists($key, $defaults) && 
	  ($defaults[$key]==true ||
	   $defaults[$key]=="yes" ||
	   $defaults[$key]==1));
  }

  // returns whether the field $key for the current entry is set
  function issetb($key) {
    return array_key_exists($key, $this->current_entry);
  }
   
  // if $key is set in the current entry, 
  // return the entry (formatted with $format)
  // otherwise return the default
  function getb($key, $default = "", $format="%s") {
    if ($this->issetb($key)) 
    return sprintf($format, $this->current_entry[$key]);
    else return $default;
  }

  function fullEntryLink($entry, $text) {
    $title = SpecialPage::getTitleFor("ExternBibShowEntry", "$entry");
    $linker = new Linker();
    $link = $linker->link($title, $text);
    return $link;
  }

  // returns the human readable filesize
  function hfilesize($file){
    $size = filesize($file);
    $i=0;
    $iec = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
    while (($size/1024)>1) {
      $size=$size/1024;
      $i++;
    }
    $size = ceil($size);
     
    return "$size $iec[$i]";
  }

  // Format $entries
  // parameters: 
  //  - abstract: show the abstract
  //  - bibtex: show the bibtexentry 
  //  - filelink: show links to corresponding files (if available)
  //  - meta: show the timestamp, owner and the key
  //  - compact: insert line breaks or not
  function format_entries($entries, $argv=array()) {
    global $wgUser;
    
    $dbname = reset($this->dbs);
    if (count($entries) == 0)
      return;

    // for backwards compatibility
    if (array_key_exists("pdflink", $argv))
      $argv["filelink"] = $argv["pdflink"];

    // set defaults
    $compact = $this->array_isset("compact", 
				  $argv, $this->default_format);
    $abstract = $this->array_isset("abstract", 
				   $argv, $this->default_format);
    $filelink = $this->array_isset("filelink", 
				   $argv, $this->default_format);
    $meta = $this->array_isset("meta", 
			       $argv, $this->default_format);
    $bibtex = $this->array_isset("bibtex", 
				 $argv, $this->default_format);
    $fullentrylink = $this->array_isset("fullentrylink", 
					$argv, $this->default_format);

    echo "<ul class=\"plainlinks\">\n";

    foreach ($entries as $entry) {
      // fetch the entry
      
      // use db name if given
      if(is_array($entry) && array_key_exists("db", $entry))
      {
         $data = dba_fetch(reset($entry), $this->dbs[$entry["db"]]);
         $dbname = $entry["db"];
         
         if (!$data) {
	   echo "<li class=\"error\">";
	   echo wfMsg('externbib-entry-notfound', reset($entry));
	   echo "</li>\n";
	   continue;
         }
         $entry = reset($entry);
      } else {
         // else check in each database if entry exists
         for (reset($this->dbs); (current($this->dbs) !== false) && !isset($data); next($this->dbs))
           $data = dba_fetch($entry, current($this->dbs));
            
         $dbname = key($this->dbs);
         
         if (!$data) {
	   echo "<li class=\"error\">";
	   echo wfMsg('externbib-entry-notfound', $entry);
	   echo "</li>\n";
	   continue;
         }
      }
      
      
      
      //if(is_array($entry) && array_key_exists("db", $entry))
      //   $dbname = $entry["db"];
      //elseif (isset($this->dbs))
      //   $dbname = key($this->dbs);
         
      reset($this->dbs);
      
      // current entry is used by getb and issetb
      $this->current_entry = unserialize($data);
      unset($data);
     
      // check whether the entry is superseded by another one
      if ($this->issetb("superseded")) {
	if ($meta) {
	  $superseded = $this->getb("superseded");
	  $supersededLink = 
	    $this->fullEntryLink($superseded, 
				 wfMsg('externbib-fullentry'));

	  echo "<li class=\"warning\">";
	  echo wfMsg('externbib-entry-superseded', 
		     $entry,
		     $superseded,
		     $supersededLink
		     );
	  echo ".</li>\n";
	}
	continue;
      } 

      echo "<li>\n";
  
      // main formatting

      if ($meta) echo "[$entry]<br/>\n";
    
      if ($this->getb("author") || $this->getb("entryType") != "book" ) {
        echo $this->getb("author", "Unknown author") . ". ";
        if (!$compact) echo "<br/>";
        echo "\n";
      }
  
      echo "<b>" . $this->getb("title", "Unknown title.", "\"%s\".") . "</b>";
      if (!$compact) echo "<br/>";
      echo "\n";

      switch ($this->getb("entryType")) {
      case "article":
	echo "<i>" . $this->getb("journal", "Unknown journal") . "</i>\n";
	echo $this->getb("volume");
	echo $this->getb("number", "", "(%s)");
	echo $this->getb("pages", "", "(%s)");
	echo $this->getb("year", "", ", <b>%s</b>");
	echo ".\n";
	break;

      case "book":
	echo $this->getb("series", "", "<i>%s</i>, ");
	echo $this->getb("publisher", "Unknown publisher", "%s");
	echo $this->getb("editor", "", ", %s");
	echo $this->getb("address", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>"); 
	echo ". \n";
	break;

      case "inbook":
      case "incollection":
	echo "In ";
      echo "<i>" . $this->getb("booktitle", "unknown booktitle") . "</i>";
      if ($this->issetb("series") || $this->issetb("volume")) {
	echo ", volume " . $this->getb("volume") . " of ";
	echo "<i>" . $this->getb("series", "") . "</i>";
      }
      echo $this->getb("chapter", "", ", chapter %s");
      echo $this->getb("pages", "", ", pages %s");
      echo ".";
      echo $this->getb("editor", "", " Editors: %s,\n");
      if (!$compact) echo "<br/>";
      echo "\n";
      echo $this->getb("publisher", "Unknown publisher");
      echo $this->getb("address", "", ", %s");
      echo $this->getb("year", "", ", <b>%s</b>");
      echo ". \n";
      break;

      case "conference":
      case "inproceedings":
	echo "<i>" . $this->getb("booktitle", "unknown booktitle") . "</i>\n";
      if ($this->issetb("series") || $this->issetb("volume")) {
	echo ", volume " . $this->getb("volume") . " of ";
	echo "<i>" . $this->getb("series", "") . "</i>";
      }
      echo $this->getb("chapter", "", ", chapter %s");
      echo $this->getb("pages", "", ", pages %s, ");
      echo $this->getb("editor", "", "Editors: %s,\n");
      echo $this->getb("address", "", ", %s");
      echo $this->getb("year", "", ", <b>%s</b>");
      echo ".";
      if (!$compact) echo "<br/>";
      echo "\n";
      echo $this->getb("publisher", "Unknown publisher");
      echo $this->getb("pubaddress", "", ", %s");
      echo ". \n";
      break;

      case "mastersthesis":
	echo $this->getb("type", "<i>Master's thesis</i>");
	echo $this->getb("school", "", ", %s");
	echo $this->getb("address", "", ", %s");
	echo $this->getb("month", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>"); 
	echo ". \n";
	break;

      case "bookreview":
	echo "Book review: ";
	echo $this->getb("title", "", ", %s");
	echo "<i>" . $this->getb("journal", "Unknown journal") . "</i>, \n";
	echo $this->getb("volume");
	echo $this->getb("number", "", "(%s)");
	echo $this->getb("pages", "", ", (%s)");
	echo $this->getb("year", "", ", <b>%s</b>"); 
	echo ". \n";
	break;

      case "phdthesis":
	echo $this->getb("type", "<i>PhD thesis</i>");
	echo $this->getb("school", "", ", %s");
	echo $this->getb("address", "", ", %s");
	echo $this->getb("month", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>"); 
	echo ". \n";
	break;
      
      case "habilitation":
	echo "<i>Habilitationsschrift</i> :";
	echo $this->getb("month", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>");
	echo ". \n";
	break;

      case "techreport":
	echo $this->getb("type", "Technical report", "%s");
	echo $this->getb("institution", "", ", %s");
	echo $this->getb("pages", "", ", pages %s");
	echo $this->getb("editor", "", ", %s, editors");
	echo $this->getb("publisher", "", ", %s");
	echo $this->getb("address", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>"); 
	echo ". \n";
	break;

      case "manual":
	echo $this->getb("publisher", "unknown publisher", "%s");
	echo $this->getb("address", "", ", %s");
	echo $this->getb("year", "", ", <b>%s</b>");
	echo ". \n";
	break;

      case "unpublished":
      case "misc":
      default:
	echo $this->getb("year", "", "<b>%s</b>.\n");
      break;
      } //end switch

      // Notes
      if ($this->issetb("note")) {
	if (! $compact) echo "<br/>\n";
	echo $this->getb("note");
      }

      // Links
      if (!$compact) echo "<br/>\n";

      // link to full entry
      if ($fullentrylink)
	echo $this->fullEntryLink($entry, '[' . 
				  wfMsg('externbib-fullentry') . 
				  ']') . "\n";

      // link to files
      if ($filelink) {
	for ($i=0; $i < count($this->filedirs); $i++) {
	  $dir = $this->filedirs[$i];
	  $urlbase = $this->filebaseurls[$i];
	  
	  $pdffile = "$dir/$entry.pdf";
	  if (file_exists($pdffile)) {
	    echo "<a href=\"$urlbase/$entry.pdf\">[PDF]</a>";
	    echo " (" . $this->hfilesize($pdffile) . ")\n";
	  }
    
	  $psfile = "$dir/$entry.ps";
	  if (file_exists($psfile)) {
	    echo "<a href=\"$urlbase/$entry.ps\">[PS]</a>";
	    echo " (" . $this->hfilesize($psfile) .")\n";
	  }

	  $psgzfile = "dir/$entry.ps.gz";
	  if (file_exists($psgzfile)) {
	    echo "<a href=\"$urlbase/$entry.ps.gz\">[PS.GZ]</a>";
	    echo " (" . $this->hfilesize($psgzfile) .")\n";
	  }
	}
      }
  
      echo $this->getb("e-print", "", 
		       "<a href=\"" . $this->eprintbaseurl . "/%s\">[Preprint]</a>\n");
      echo $this->getb("doi", "",
		       "<a href=\"" . $this->doibaseurl . "/%s\">[DOI]</a>\n");
      echo $this->getb("url", "", "<a href=\"%s\">[URL]</a>\n");
	
      // Abstract
      if ($abstract && $this->issetb("abstract")) {
	echo "<div style=\"margin:0pt 1em 1em 1em;font-size:75%\">\n";
	echo $this->getb("abstract");
	echo "</div>\n";
      }

      // Timestamp
      if ($meta && 
	  ($this->issetb('timestamp') || $this->issetb('owner'))) {
	echo "<div style=\"margin-left:1em;font-size:90%;\">";
	if ($this->issetb('timestamp') && $this->issetb('owner'))
	  echo wfMsg('externbib-enteredon', 
		     $this->getb("owner"), $this->getb("timestamp"), $dbname);
	elseif ($this->issetb('timestamp'))
	  echo wfMsg('externbib-enteredon-noowner', 
		     $this->getb("timestamp"), $dbname);
	else
	  echo wfMsg('externbib-enteredon-notimestamp', 
		     $this->getb("owner"), $dbname);

	echo "</div>\n";
      }

      // BibTeX record
      if ($bibtex && $this->issetb("fullEntry")) {
	echo "<pre>\n";
	echo $this->getb("fullEntry");
	echo "</pre>\n";
      }
    
      echo "</li>\n";
    } //end foreach

    echo "</ul>\n";
  } // end format_entry  

  // parse the query string
  function parse_query($querystring) {
    $s = trim($querystring);
    $error = 0;
    do {
      // match the key
      if (!preg_match('/^\s*(\w+)/', $s, $match)) 
	return "Cannot parse key in \"$s\"!";
      $key = $match[1];
    
      // advance the string
      $offset=strlen($match[0]);
      $s = substr($s, $offset);
    
      // match the op
      if (preg_match('/^\s+contains\s+/', $s, $match)
	  || preg_match('/^\s*=/', $s, $match))
	$op = "contains";
      elseif (preg_match('/^\s+greater\s+/', $s, $match) ||
	      preg_match('/^\s*\>/', $s, $match))
	$op = "greater";
      elseif (preg_match('/^\s+less\s+/', $s, $match) ||
	      preg_match('/^\s*\</', $s, $match))
	$op = "less";
      else return "Cannot parse operator in \"$s\"!";
    
      // advance the string
      $offset=strlen($match[0]);
      $s = substr($s, $offset);
    
      if (!preg_match('/^\s*\"([^\"]*)\"/', $s, $match) &&
	  !preg_match('/^\s*(\S+)/', $s, $match))
	return "Cannot parse searchvalue in \"$s\"";

      $searchvalue = $match[1];
    
      // advance the string
      $offset=strlen($match[0]);
      $s = substr($s, $offset);
    
      $query[] = array($key, $op, $searchvalue);
    
      $and = preg_match('/^\s*and\s*/', $s, $match);
      if ($and) {
	// advance the string
	$offset=strlen($match[0]);
	$s = substr($s, $offset);
      }
    } while ($and);
  
    if (!preg_match('/^\s*$/', $s)) {
      return "Cannot parse continuing string \"$s\"!";
    }

    return $query;
  }

  function search_entries($querystring, $databases = array()) {
    //search in all databases if none specified
    
    if (!is_array($databases))
       $databases = array($databases => $databases);
    elseif (count($databases) == 0)
       $databases = array_keys($this->dbs);
       
    $query = $this->parse_query($querystring);
    if (!is_array($query)) return $query;

    // fetch all entries of the given databases into data array
    if (!isset($this->data)) {
       foreach($databases as $database)
       {
          $entry = dba_firstkey($this->dbs[$database]);
          while ($entry) {
	     $record=unserialize(dba_fetch($entry, $this->dbs[$database]));
	     $this->data[$entry] = array_merge($record, array("db" => $database));
	     $entry = dba_nextkey($this->dbs[$database]);
          }
       }
    }

    // now query the data
    $selection = array_keys($this->data);
    if (is_array($selection) && count($selection) > 0)
    {
       foreach ($query as $phrase) {
         $newselection = array();
         $key=$phrase[0];
         $op=$phrase[1];
         $searchvalue=$phrase[2];
      
         switch ($op) {
         case "contains":
	   foreach ($selection as $entry) {
	     if (array_key_exists($key, $this->data[$entry])) {
	       $value = $this->data[$entry][$key];
	       if (mb_strpos(mb_strtolower($value), mb_strtolower($searchvalue)) !== FALSE)
	         $newselection[] = $entry;
	     } 
	   }
	   break;
         case "greater":
	   foreach ($selection as $entry) {
	     if (array_key_exists($key, $this->data[$entry])) {
	       $value = $this->data[$entry][$key];
	       if ($value > $searchvalue)
	         $newselection[] = $entry;
	     }
	   }
	   break;
         case "less":
	   foreach ($selection as $entry) {
	     if (array_key_exists($key, $this->data[$entry])) {
	       $value = $this->data[$entry][$key];
	       if ($value < $searchvalue)
	         $newselection[] = $entry;
	     }
	   }
	   break;
         }
         $selection = $newselection;
       }
    }
    foreach ($selection as $entry)
    {
       $ret_selection[] = array($entry, "db" => $this->data[$entry]["db"]);
    }
    return $selection;
  }
}
