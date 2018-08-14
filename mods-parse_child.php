<?php
// Carolyn added endDate parsing 2018-07-20

date_default_timezone_set('UTC');

// FILL IN DIRECTORY ON COMMAND LINE
$file = "";
$LOCALDIR = "";
$parentxml = "";

// 1. get file from command line
if (count($argv) < 4) {
	//print_r($argv);
	//print count($argv);
	usage();
	exit;
}

else {
        $file = $argv[1];
        $LOCALDIR = $argv[2];
        $parentxml = $argv[3];
}


if (stristr($file,".txt")) {
		// output -- individual MODS records based on filename
		echo "NOW PARSING FILE: $file\n";
		$filecontents = file_get_contents($file);
		// get fields from first line
		$fields = getFields($filecontents);
		// get file contents without first line of field headers
		//$filecontents = stripfileheaders($filecontents,$fields);
		$records = parseFile($file,$filecontents,$fields,$LOCALDIR,$tableOfContents,$identifier,$description,$parentxml);
		// parseFile calls makeRecord and writeFile 
} // end IF $file

0;

function usage() {
	echo "To use this script, enter the ABSOLUTE paths to the tab-delimited you wish to parse.\nexample: /usr/bin/php " . $_SERVER['PHP_SELF'] . " /path/to/files/myFile.txt /path/to/MODS/directory /parent/xmlfile/path.xml \nOnly tab-delimited files can be parsed.\n\n";
}

function stripfileheaders($filecontents, $fields) {
	echo "In stripfileheaders\n";
	$headers = implode("\t",$fields) . "\r\n"; // make a string of fields
	return(str_replace($headers,"",$filecontents));
}

function getFields($filecontents) {
	echo "In getFields\n";

	// assumed: first line is fields
	$contentsA = explode("\r",$filecontents);
	$firstlineA = '';
	
	$count = strlen($contentsA[0]);

	if ($count > 0) {
		$firstlineA = explode("\t",$contentsA[0]);
	}

	return $firstlineA;
} // end FUNCTION getFields($filecontents)


function parseFile($file,$filecontents,$fields,$LOCALDIR,$tableOfContents,$identifier,$description,$parentFile) {
	echo "In parseFile\n";

        // get records (headers already removed in stripheaders function)
       // $records = explode("\r\n",$filecontents);
      $records = explode("\n",$filecontents);

		// debug
		//print_r($records);
		//return;	
			
		$validFields = array(     	"title"=>"1",
									"description"=>"2",
									"date"=>"3",
									"identifier"=>"0",
									"filename"=>"0",
									"page" => "0",
									"object" => "0",
									"dateEnd"=>"4"
									//"dateOther" => "4", 
		); 
		
	$parent = getParent($parentFile);

	$pointer = 0; // keep tabs on where we are
	$recordCount = count($records);

	// words that will come up in page containers
	$pagePattern = "/^Page \d?\d?\d/";


	
	// this process will make ITEM-LEVEL MODS RECORDS
	foreach($records as $record) {
		$pointer++;
		echo "now parsing $record\n";
		trim($record);
		$record = str_replace($RETURNCR, "\r", str_replace($RETURNLF, "\r", $record)); // get out line breaks...?

		$record = explode("\t",$record);	
		
		// title
		$title = trim($record[$validFields["title"]]);		

		$xmlrecord = makeRecord($record, $validFields, $parent, $inclusionArray);	
		writeFile($xmlrecord[0],$xmlrecord[1], $LOCALDIR);
		
		} // end FOREACH record
		
} // end FUNCTION parseFile

function getParent($parentXmlPath) {

	  $parentXml = file_get_contents($parentXmlPath);
	  
	// get variables and return an associative array with some useful stuff
	  $proc = new XsltProcessor();
	  $input = new DomDocument();
	  $input->loadXML($parentXml);

  	  $xpath = new DOMXPath($input);
	  $mods_path = "//*[name()='mods']";
	  $mods = $xpath->query($mods_path);
	  	  
	  // DATE
	  $parent_path = "//*[name()='originInfo']"; 
	  $dateStart_path = "//*[@point='start']";
	  $dateEnd_path = "//*[@point='end']";
	  $dateOther_path = "//*[name()='dateOther']";
	  $dateStart = $xpath->query($dateStart_path);
	  $dateEnd = $xpath->query($dateEnd_path);
	  $parent = $xpath->query($parent_path);
  	  $dateOther = $xpath->query($dateOther_path);

	  $dateStartString = $dateStart->item(0)->nodeValue;
	  $dateEndString = $dateEnd->item(0)->nodeValue;
	  $dateOtherString = $dateOther->item(0)->nodeValue;
	  
	 // TITLE
	 $title_path = "//*[name()='title']";
	 $title = $xpath->query($title_path);
	 $titleString = $title->item(0)->nodeValue;
	 
	 $parent_array = array("title"=>$titleString, "dateOther"=>$dateOtherString, "dateStart"=>$dateStartString, "dateEnd"=>$dateEndString);
	//print_r($parent_array);
	//exit;
	return ($parent_array);
	
} // end FUNCTION getParent


function makeRecord($record, $validFields, $parent, $tableOfContents = NULL ) { 
	echo "In makeRecord\n";

		// PARSE THE RECORD
		$xmlheader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$modsheader = '<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="3.2" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-2.xsd">' . "\n";
		$modsfooter = "</mods>\n";
		$recordcontents = $modsheader;
	
		// FROM THE PARENT
		$parentTitle = $parent['title'];
		$parentDateOther = $parent['dateOther'];
		$enddate = $parent['dateStart'];
		$startdate = $parent['dateEnd'];

		// get page range
		/* cases:
		 * Adelaide-Claflin-Mansfield-1897_0611 -- the page
		 * Adelaide-Claflin-Mansfield-1897_0611, 0615-0617 -- the page, but enclosure starts later
		 * Adelaide-Claflin-Mansfield-1897_0619, 0623 -- the page, enclosure starts later, no range
		 * Adelaide-Claflin-Mansfield-1897_0003-0012 -- range of pages consecutive from parent page
		 * 
		 */
		
		// No ranges in new format
		/*
		$number_pages = 1; // assume 1
		$current_page = 1;
		$pattern = "/\d\d\d\d-\d\d\d\d/";
		if (preg_match($pattern, $record[$validFields["page"]], $matches)) {
			$last_page = (int) substr($matches[0],-4);
			echo $last_page . "\n";
			$first_page = (int) substr($matches[0], 0, 4);
			echo $first_page . "\n";
			$number_pages = (int) ($last_page - $first_page);
		}
		*/
		$pattern = "/_\d\d\d/";
		if (preg_match($pattern, $record[$validFields["identifier"]], $matches)) {
			$current_page = (int) substr($matches[0],-3);
		}
		
		// title
		$title = trim($record[$validFields["title"]]);		
		$recordcontents .= "<titleInfo>\n\t<title>$title</title>\n</titleInfo>\n";
		
		// dates
		// from OpenRefine, assume if it's a date in the first column, its w3cdtf format
		$childDate = trim($record[$validFields['date']]);
		$childDateEnd = trim($record[$validFields['dateEnd']]); 
		$childDateOther = ''; 
		
		// transform child date into string
		if ($childdate != '') {
			$childDateOther = date_create_from_format('j F YY',strtotime($childDateOther));
			$dateOther = $childDateOther;
		}
		else {
			$dateOther = "From scrapbook dated " . $parentDateOther;
		}
		
		if ($childDate != '') {
			$startdate = $childDate;
			$enddate = $childDateEnd;
		}
		else {
			$startdate = $parent['dateStart']; // ASSUMPTION - PARENTS HAVE VALID DATES
			$enddate = $parent['dateEnd'];
			//$dateOther = $parentDateOther;
		}
	echo "parent date other = $parentDateOther; dateOther = $dateOther|\n";
	//break;
		// date	and publishing info - originInfo
		$recordcontents .= "<originInfo>\n";
		$recordcontents .= "\t<dateOther>" . $dateOther . "</dateOther>\n";
		if ($startdate != '') {
			$recordcontents .= "\t" . '<dateCreated authority="w3cdtf" point="start">' . $startdate . '</dateCreated>' . "\n";
		}
		if ($enddate != '') {
			$recordcontents .= "\t" . '<dateCreated authority="w3cdtf" point="end">' . $enddate . '</dateCreated>' . "\n";
		}
		$recordcontents .= "\t<publisher>Online collection published by Vassar College Libraries, Poughkeepsie, N.Y.</publisher>\n"; 

		$recordcontents .= "</originInfo>\n";
		$recordcontents .= "<note>Included in " . $parent['title'] . "</note>\n";
		
		$tableOfContents = '';
		
		// Table of Contents TODO
		/*$toc = '';
		if (!is_null($tableOfContents)) {
			foreach($tableOfContents as $tableOfContent) {
				$toc .= $tableOfContent . '\n';
			}
		} 
		
		if ($toc != '')
			$recordcontents .= "\t<tableOfContents>$toc</tableOfContents>\n";
		*/
		
		echo "DESCRIPTION: " . $record[$validFields['description']] . "\n";
		
		if (!is_null($record[$validFields['description']]) && $record[$validFields['description']] != '') {
			$recordcontents .= "<tableOfContents>" . trim($record[$validFields['description']]) . "</tableOfContents>\n";
		}
		// extent
		/*
		$recordcontents .= "<physicalDescription>\n\t<extent>" . $number_pages . " page"; 
		if ($number_pages != 1)
			$recordcontents .= "s";
		$recordcontents .= "</extent>\n";
		//$recordcontents .= "\t<form authority=\"marcform\">print</form>\n";
		$recordcontents .= "</physicalDescription>\n";
		*/
		// relation, rights, source, publisher 
		$recordcontents .= "<accessCondition type=\"use and reproduction\">For more information about rights and reproduction, visit http://specialcollections.vassar.edu/policies/permissionto.html</accessCondition>\n";	

		// identifier
		$filename = $record[$validFields["identifier"]];
		$recordcontents .= "<identifier type=\"local\">" . $record[$validFields["object"]] . "</identifier>\n";
		
		echo "filename is: " . $filename . "\n";

		$filename = str_replace('.','-',$filename);
		echo "transformed filename is: " . $filename . "\n";
		if ($record[$validFields['filename']] == '')
			$filename = $identifier . ".xml";
			

		$recordcontents .= $modsfooter;
        
		$recordcontents = str_replace("&", "&amp;", $recordcontents);
		$recordcontents = str_replace("<br />", "&lt;br /&gt;", $recordcontents);
		
		$recordInfo = array($recordcontents,$filename);
		//print_r($recordInfo);
    return $recordInfo;

} // end FUNCTION makeRecord

function writeFile ($filecontents, $filename, $LOCALDIR) {
//function writeFile ($filecontents, $tableOfContents, $filename, $LOCALDIR) {

	echo "LOCAL DIRECTORY: $LOCALDIR\n";
	echo "FILENAME: $filename\n";

	if (substr($filename,-3,3) != "xml")
		$filename .= ".xml";

	$directory = $LOCALDIR;

	if (!file_exists($directory)) {
		echo "creating $directory\n";
		mkdir($directory);
	}
	chdir($directory);

	if (!file_exists($filename)) {
		echo "creating $filename\n";
		touch($filename);
	}
	
	$fhandle = fopen($filename, 'w+');
	fputs($fhandle,$filecontents);
	fclose($fhandle);

	echo "just wrote $filename\n";	
	
	// write out text
	//$filename = str_replace("xml","txt",$filename);
//		$fhandle = fopen($filename, 'w+');
//	fputs($fhandle,$tableOfContents);
//	fclose($fhandle);

	echo "just wrote $filename\n";	
	chdir("../");
			
} // end FUNCTION writeFile

?>
