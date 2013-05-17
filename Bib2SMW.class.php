<?php
if (!defined('MEDIAWIKI')) die();

class Bib2SMW {
  // The Page which has all the entrys
  var $database;
  
  // parameters
  var $dbpage;


  function Bib2SMW() {
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
	    foreach($t1 as $key => $value){
	      $b=str_replace(array('[',']'),array('&#91;','&#93;'),$value);
	      $b=str_replace(array('{','}'),array('',''),$b);
	      // process all data manualy
	      switch ($key){
	      case "year":
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
		array_push($data,"BibTeX_year=$c");
		break;
	      default:
		echo $key," => ",$value,"<br>";
		break;
		

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
	  
	  // For debug: do not render stuff
	  //$err = call_user_func_array(array('SMWSubobject','render'),$refParams);
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

}
