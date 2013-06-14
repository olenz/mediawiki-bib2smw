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
    $bibName=substr($title,$len+1);
    if ( ! substr($title,1,$len-1) === substr($wgBibTeXDBPage,1,-1)){
      $error.="Not called from a valid page"; return $error;
    }
    if ( strncmp($title,$wgBibTeXDBPage,1) != 0){
      $error.="Not called from a valid page"; return $error;
    }
    $dbid=substr($title,$len);
    if ($bibName==''){
      $error.="Not called from a valid page"; return $error;
    }
    $xmlpath=$wgBibTeXXMLPath.$bibName.'.xml';
    if (!file_exists($xmlpath)){
      $error.="Not called from a valid page"; return $error;
    }
    $clearfile=$wgBibTeXXMLPath.$bibName.'.clear';
    $dbid=(int) $dbid;
    $ins=explode(',',$input);
    $from=0;
    $step=$wgBibTeXDBSize;
    if (!isset($_SERVER['SERVER_ADDR']))
      $isscript=true;
    else
      $isscript=false;
    if (isset($_GET['enforce'] ) && $_GET['enforce']=='clearSMW'){
      touch($clearfile);
      $error.="Disabled SMW<br>Refresh SMW is required<br>";
    }
    if (isset($_GET['undo'] ) && $_GET['undo']=='clearSMW'){
      $error.="Activated SMW<br>Refresh SMW is required<br>";
      unlink($clearfile);
    }
    if ( $isscript ||$_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR'] || (isset($_GET['enforce'] ) && $_GET['enforce']=='updateDB') || $nocheck){
      //print_r($GLOBALS);
      //die();
      if (file_exists($clearfile)){
	$error.="Page is set to be cleared";
	echo "Page is set to be cleared\n";
	return $error;
      }
      $page=new WikiPage(Title::newFromText($title));
      $page->clear();
      $to=$from+$step;
      $GLOBALS['wgLangConvMemc']->expireAll();
      return $this->doUpdateDB($parser, $xmlpath, $from, $to,$error);
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
    if (!isset($_SERVER['SERVER_ADDR']))
      $isscript=true;
    else
      $isscript=false;
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
	  foreach($entry as $type => $toparse){
	    array_push($data,"BibTeX_type=$type");
	    foreach($toparse as $key => $value){
	      $b=str_replace(array('[',']'),array('&#91;','&#93;'),$value);
	      $b=str_replace(array('{','}'),array('',''),$b);
	      // process all data manualy
	      switch ($key){//unprocessed string values
	      case "title":
	      case "title":
	      case "journal":
	      case "pages":
	      case "abstract":
	      case "note":
	      case "superseded":
	      case "number":
	      case "volume":
		$value=trim($value);
	        if ($value == "")
		 continue;
		array_push($data,"BibTeX_$key=$value");
		break;
	      case "DoesNotJetExist":
		// int values, warn on change
		$c=(int)$value;
		if (strcmp("$c" , $value)){
		  $msg="Warning: $key@$cur_id: changed $value to $c<br>\n";
		  if ($isscript){ echo $msg; }else{ $error .= $msg;}
		}
		break;
	      case "url":
		$value=trim($value);
	        if ($value == "")
		 continue;
		$url = parse_url($value);
		if (!isset($url['scheme'])){
		  $value="http://$value";
		}
		array_push($data,"BibTeX_$key=$value");
		break;
	      case "doi":
		$value=trim($value);
		if (strncasecmp($value,"DOI:",4)==0){
		  $value=substr($value,4);
		  $value=trim($value);
		}
	        if ($value == "")
		 continue;
		array_push($data,"BibTeX_$key=$value");
		break;
	      case "keywords":
	      case "keyword":
	      case "key":
		$value=trim($value);
	        if ($value == "")
		 continue;
		array_push($data,"BibTeX_keywords=$value");
		break;
	      case "author":
		$tmp=true;
		foreach ($value as $pers){
		  $pers=trim($pers);
		  if ($pers == "")
		    continue;
		  array_push($data,"BibTeX_$key=$pers");
		  $tmp=false;
		}
		if ($tmp)
		  array_push($data,"BibTeX_$key=$value");
		break;
	      case "eprint":
	      case "e-print":
		array_push($data,"BibTeX_eprint=$value");
		break;
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
		array_push($data,"BibTeX_$key=$value");
		array_push($data,"BibTeX_year_int=$c");
		break;
	      case "file":
	      case "nstandard":
		$b=explode(":",$value);
		$s=count($b);
		/*if ($b[0] != $b[1]){
		  $msg="Warning: $key@$cur_id: $value\n";
		  if ($isscript){ echo $msg; }else{ $error .= $msg;}
		}*/
		if ($s > 1){
		  if ($b[$s-1]=="PDF"){
		    $value=$b[$s-2];
		  }
		  else{
		    $msg="Warning: $key@$cur_id: $value\n";
		    array_push($data,"BibTeX_unknown=$key => $value");
		  }
		}
		$b=basename($value);
		if (file_exists("/mnt/home_anoa/icpwiki/bib/".$b)){
		  array_push($data,"BibTeX_pdf=$b");
		  array_push($data,"BibTeX_pdf_size=".$this->size2str(filesize("/mnt/home_anoa/icpwiki/bib/".$b)));
		}
		elseif (file_exists($value)){
		  array_push($data,"BibTeX_pdf=$value");
		  array_push($data,"BibTeX_pdf_size=".$this->size2str(filesize($value)));
		}
		else{
		  array_push($data,"BibTeX_pdf=$value");
		}
	      case "timestamp"://ignore these
	      case "owner":
	      case "address":
	      case "publisher":
	      case "month":
	      case "date-modified":
		break;
	      default:
		array_push($data,"BibTeX_unknown=$key => $value");
		$error.=$key."@$cur_id => ".$value."<br>";
		break;
		

	      }
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
	  $err = call_user_func_array(array('SMWSubobject','render'),$refParams);
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

  function size2str($size){
    $k=1024;
    if ($size<$k){
      return "$size B";
    }
    $k*=1024;
    if ($size<$k){
      $k/=1024.;
      $size=Bib2SMW::round($size/$k,2);
      return "$size kB";
    }
    $k*=1024;
    if ($size<$k){
      $k/=1024.;
      $size=Bib2SMW::round($size/$k,2);
      return "$size MB";
    }
    $k*=1024;
    $k/=1024.;
    $size=Bib2SMW::round($size/$k,2);
    return "$size GB";
  }

  function round($num,$dig){
    $t=$num;
    $c=0;
    while ($t > 10){
      $t/=10;
      $c++;
    }
    return round($num,$dig-$c);
  }

}