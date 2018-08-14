<?php

// FILL IN DIRECTORY ON COMMAND LINE
$file = "";
$LOCALDIR = "";

// 1. get file from command line
if (count($argv) < 3) {
	//print_r($argv);
	//print count($argv);
	usage();
	exit;
}

else {
        $file = $argv[1];
        $LOCALDIR = $argv[2];
}

	if (stristr($file,".txt")) {
		// output -- individual MODS records based on filename
		echo "NOW PARSING FILE: $file\n";
		$filecontents = file_get_contents($file);
		// get fields from first line
		$fields = getFields($filecontents);
		// get file contents without first line of field headers
		$filecontents = stripfileheaders($filecontents,$fields);
		$records = parseFile($file,$filecontents,$fields,$LOCALDIR);
	}


0;

function usage() {
	echo "To use this script, enter the directory of the tab-delimited files you wish to parse.\nexample: /usr/bin/php " . $_SERVER['PHP_SELF'] . " /path/to/files/.\nOnly tab-delimited files can be parsed.\n\n";
}

function stripfileheaders($filecontents, $fields) {
	$headers = implode("\t",$fields) . "\r"; // make a string of fields
	return(str_replace($headers,"",$filecontents));
}

function getFields($filecontents) {
	// assumed: first line is fields
	$contentsA = explode("\r",$filecontents);
	$firstlineA = '';
	
	$count = strlen($contentsA[0]);

	if ($count > 0) {
		$firstlineA = explode("\t",$contentsA[0]);
	}

	return $firstlineA;
} // end FUNCTION getFields($filecontents)


function parseFile($file,$filecontents,$fields,$LOCALDIR) {

        // first, get rid of "vertical tabs"
        // any time anyone hit "enter" in the filemaker contents you will get one
        // VTAB is ASCII decimal character code 11, in vim it comes up as ^k
        // easiest to just use the chr() function to assign it to a var
        $VERTICALTAB = chr(11);
        // if there are rogue carriage returns that are non-Unixy, then note that
        if (stristr($filecontents,$VERTICALTAB))
                echo "\t\t** CARRIAGE RETURNS FOUND IN FILE $file ** \n";

        $filecontents = rtrim(str_replace("$VERTICALTAB", "; ", $filecontents));

	// get rid of XML special characters
	// THIS DOESN'T WORK YET -- BECAUSE CONTENTDM DELIMITS SUBJECTS, ETC., WITH SEMICOLONS...
        //$filecontents = str_replace("&", "&amp;", $filecontents);

        $FILEEXTENSION = ".jpg";

        $RETURNLF = chr(10);
        $RETURNCR = chr(13);
        $TAB = chr(9);

		// problem: some of the files have a carriage return within the fields.  this made excel do something more funky: put
		// quotes around the fields but keep the carriage return as \r.  so need to sniff for this...

		if (stristr($filecontents, $RETURNLF . '"')) {
			// strip out the funk: 
			echo "\t\tFUNK IN FILE $file.\n";
			$filecontents = str_replace($RETURNLF . '"', '"', $filecontents);
		}
		
		// need to replace all \r with \n etc.  ^M is chr(13)
		// make everything to be "\r" since we're on OSX?
		
		if (stristr($filecontents,$RETURNCR))
                echo "\t\t** CARRIAGE RETURNS FOUND IN FILE $file ** \n";
                
        if (stristr($filecontents,$RETURNLF))
                echo "\t\t** LINE FEEDS FOUND IN FILE $file ** \n";
		
		// convert carriage returns to line feeds
		
		$filecontents = str_replace($RETURNCR,$RETURNLF,$filecontents);
		
		// while we're at it, this is tab delimited, dammit.  so strip out everything that is:
		// return + quote (already done)
		// quote + tab
		// tab + quote
		// quote + return
		
		$filecontents = str_replace('"' . $TAB, $TAB, $filecontents);
		$filecontents = str_replace($TAB . '"', $TAB, $filecontents);
		$filecontents = str_replace('"' . $RETURNLF, $RETURNLF, $filecontents);
		$filecontents = str_replace($RETURNLF. '"', $RETURNLF, $filecontents);
	
		// brute force: get the very first quote
		if (substr($filecontents, 0, 1) == '"')
			$filecontents = substr($filecontents, 1);
		
        $dateran = '';

        // second, get records (headers already removed in stripheaders function)
       // $records = explode($RETURNLF,$filecontents);
       $records = explode("\n", $filecontents);
		// debug
		//print_r($records);
		//return;
		
		

		
	$xmlheader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$modsheader = '<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="3.2" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-2.xsd">' . "\n";
	$modsfooter = "</mods>\n";

	// THIS IS BASED ON INDIVIDUAL COLLECTION

    // here's an idea -- place the field number in the mapping below
    // if the field doesn't map, place $badflag
    $badflag = 999;

    $validFields = array(     	"title"=>"3",
                                "creator"=>"2",
                                "date"=>"7",
                                "startdate" => "5",
                                "enddate" => "6",
                                "extent"=>"8",
                                "filename"=>"0",
                                "affil" => "1",
                                "subjects"=>"9",
                                "cwsubjects"=>"10",
         ); // TODO better?


	// IGNORING "FILE" FIELD -- FILE IS IDENTIFIER + JPG	

	foreach($records as $record) {
		//$record = str_replace($RETURNCR, "\r", str_replace($RETURNLF, "\r", $record)); // get out line breaks...?
		$recordcontents = $xmlheader . $modsheader;
		$record = explode("\t",$record);
	
		//print_r($record);
		if ($record[0] == '')
			continue;

		echo "Processing {$record[0]}...\n";
		//return;

		// record is now an array of fields whose order matches above validFields

		// manually create the MODS record	

		// strip out wacky quotes
		// text files seem to also have ^M in them... which character is this?
		for($i = 0; $i < count($record); $i++) {
			if (substr($record[$i],0,1) == '"') {
				$record[$i] = substr($record[$i],1,strlen($record[$i]));
				//echo "just transformed record $i to $record[$i]\n";
			}

			if (substr($record[$i],-1) == '"') {
				$record[$i] = substr($record[$i],0,-1);
				//echo "just transformed record $i to $record[$i]\n";
			}
		} 

// MODS fields that we want

// first, need to move around some title/date issues

$date = $record[$validFields["date"]];
$enddate = $record[$validFields["enddate"]];
$startdate = $record[$validFields["startdate"]];

if ($startdate != "" && $startdate != "n.d.")
	$startdate .= "T00:00:01Z";

if ($enddate != "" && $startdate != "n.d.")
	$enddate .= "T23:59:59Z";

		// title
		$title = rtrim($record[$validFields["title"]]);		
		$recordcontents .= "<titleInfo>\n\t<title>" . $title . "</title>\n</titleInfo>\n";
	
		// creator, contributor
		if ($record[$validFields["creator"]] != "") {
			$creators = split(';', $record[$validFields['creator']]);
			foreach ($creators as $creator) {
				trim($creator);
				$recordcontents .= "<name type=\"personal\">\n\t<namePart>" . $creator . "</namePart>\n";
				$recordcontents .= "\t<role>\n\t<roleTerm type=\"text\">creator</roleTerm>\n\t</role>\n";
				if ($record[$validFields['affil']] != "")
					$recordcontents .= "\t<affiliation>" . $record[$validFields['affil']] . "</affiliation>\n";
				$recordcontents .= "</name>\n";
			}
		}

	
	
		// date	and publishing info - originInfo
		$recordcontents .= "<originInfo>\n";
		$recordcontents .= "\t<dateOther>$date</dateOther>\n";
		if ($startdate != '') {
			$recordcontents .= "\t" . '<dateCreated encoding="w3cdtf" point="start">' . $startdate . '</dateCreated>' . "\n";
			$recordcontents .= "\t" . '<dateCreated encoding="w3cdtf" point="end">' . $enddate . '</dateCreated>' . "\n";
		}
		$recordcontents .= "\t<publisher>Online collection published by Vassar College Libraries, Poughkeepsie, N.Y.</publisher>\n"; 

		$recordcontents .= "</originInfo>\n";

        // genre
        // in finding aid, all letters are in series 1
        $recordcontents .= "<genre authority=\"marcgt\">scrapbooks</genre>\n";

		// subject
		$subjects = explode("|",$record[$validFields["subjects"]]);
		 //$subjects = array("Women college students--New York (State)--Poughkeepsie.");
		 if (count($subjects) > 0) {
			$recordcontents .= "<subject authority=\"lcsh\">\n";
			foreach ($subjects as $subject) {
				if ($subject != "")
					$recordcontents .= "\t<topic>" . $subject . "</topic>\n";
			}
			$recordcontents .= "</subject>\n";
		}
		
		$cwsubjects = explode("|",$record[$validFields["cwsubjects"]]);
		 //$subjects = array("Women college students--New York (State)--Poughkeepsie.");
		 if (count($cwsubjects) > 0) {
			$recordcontents .= "<subject authority=\"local\">\n";
			foreach ($cwsubjects as $cwsubject) {
				if ($cwsubject != "")
					$recordcontents .= "\t<topic>" . $cwsubject . "</topic>\n";
			}
			$recordcontents .= "</subject>\n";
		}

// IN THIS CASE, WE WANT A NOTE FIELD FOR ECS
		$abstract = $record[$validFields['affil']];
		$recordcontents .= "<note>$abstract</note>\n";

		// extent
		$recordcontents .= "<physicalDescription>\n\t<extent>" . $record[$validFields["extent"]] . " p.</extent>\n";
		//$recordcontents .= "\t<form>" . $record[$validFields["form"]] . "</form>\n";
		$recordcontents .= "</physicalDescription>\n";

		// relation, rights, source, publisher 
		$recordcontents .= "<accessCondition type=\"use and reproduction\">For more information about rights and reproduction, visit http://specialcollections.vassar.edu/policies/permissionto.html</accessCondition>\n";	

		$identifier = $record[$validFields["filename"]];

		$recordcontents .= "<identifier type=\"local\">" . $identifier . "</identifier>\n";
		
		echo $identifier . "\n";

		$filename = $record[$validFields["filename"]] . ".xml";
		echo "filename is: |" . $filename . "|\n";
		trim($filename);
		//exit;
		
		//$LOCALDIR = "/Users/jodipasquale/Documents/Projects/studentletters/metadata/2018-large-letter-set-metadata/mods/";
			
        echo "filename is: $LOCALDIR/" . $filename . "\n";
        
		$recordcontents .= $modsfooter;
        
		$recordcontents = str_replace("&", "&amp;", $recordcontents);
		$recordcontents = str_replace("<br />", "&lt;br /&gt;", $recordcontents);
		
		// need one MODS file per record...?
		if ($filename != "Filename") {
		    if ($filename == "") {
		    	//$filename = str_replace(".txt","",$file) . "-" . rand() . ".xml"; // just in case we have a few blanks
		    	$filename = str_replace(".txt",".xml",$file); // just in case we have a few blanks
			}
			writeFile($recordcontents,$filename, $LOCALDIR);
		}
	}
        return $recordcontents;

} // end FUNCTION parseFile

function writeFile ($filecontents, $filename, $LOCALDIR) {
echo "LOCAL DIRECTORY: $LOCALDIR\n";
//exit;

	if (stristr($filename,".jpg"))
		$filename = str_replace(".jpg",".xml",$filename);

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
	chdir("../");
			
} // end FUNCTION writeFile

?>
