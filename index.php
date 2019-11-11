<?php
// webapps.library.queensu.ca/find-on-floor-plan/index.php?loc=<location-code>&cn=<call-number>

// NOTE: If this file is redirected via php function call header(), post map look up.
// If white space is present prior to this opening PHP block, redirect will fail.
// With Warning;
// Warning: Cannot modify header information - headers already sent by
// (output started at /var/www/html/qcat-floorplan/qcat-find-on-floorplan.php:1)
// in /var/www/html/qcat-floorplan/qcat-find-on-floorplan.php on line 43
//
// NOTE: ADACOSTA - Oct 5, 2017
// Discovered that QCAT omits call numbers when the item is non-circulating (reserve item)
// Modifying code to handle this case.
// No cn, set special case to "reserve item".
// Added CSV records for each QCAT location code series to support locating
// Information Services at the required location.
//
// NOTE: ADACOSTA - Oct 15, 2017
// Relaxed if statement check if second parts of call number and end range are set
// Removed the if end range set clause. We
//
// NOTE: ADACOSTA - Mar 27, 2019
// Added Special case #0 Microform - Art collection microforms are now ll stauffer
// QUL Solution ticket 57,601
//
// NOTE: ADACOSTA - Oct 23, 2019
// Added check for "|" pipe character as variable delimination
// PrimoVE bug where "&" ampersand character being replaced with &amp; in URL.
// ../find-on-floorplan/?loc=sl|cn=%20PS8319.O73%20E56%202018
//
// NOTE: ADACOSTA - Nov 04, 2019
// Bug fixes, modifications for new mappings from PrimoVE, HTML output styling.
// Supporting AC123, ZZ123 call numbers. A123, AA123 through to Z123, ZZ123.
// Corrected erroneous results for call numbers that fall within range breaks;
//    - such as range A = "A, BF123"; range B = "BX123, ZZ"
//    - call numbers BF124 to BX122 would resolve incorrectly, should actual result in "no map location for this item"
//    - if mapping range ends in "Z", then ZA now results in "no map location for this item" (expected behaviour)
//    - if mapping range ends in "ZZ", then ZA now locates correct map (expected behaviour)


// request values --------------------------------------------------------------
$request_location = "";
if (isset($_GET['loc'])) {
  $request_location = $_GET['loc']; // QCAT location code
}
$request_call_number = "";
if (isset($_GET['cn'])) {
  $request_call_number = trim(urldecode($_GET['cn'])); // call numbers to further refine location
  $request_call_number = strtoupper($request_call_number);
}

$using_pipe = false; // flag to track if we found & or | separating loc and call number (QCat versus PrimoVE)
                    // Used to provide specific styling to map image if PrimoVE

// Check request uri values for PIPEs - PrimoVE bug, replacing & with &amp; (separating request variables)
if ((isset($_SERVER['REQUEST_URI']) && ($_SERVER['REQUEST_URI'] !== ''))) {
  if (strpos($_SERVER['REQUEST_URI'],"|")) {

    $using_pipe = true;

    $test_uri = $_SERVER['REQUEST_URI'];
    $test_for_vars = stristr($test_uri,"?");

    $this_array = explode("|",$test_for_vars);
    $this_array_loc = $this_array[0];
    $this_array_cn = $this_array[1];

    $request_location_loc = explode("=",$this_array_loc);
    $request_location_cn = explode("=",$this_array_cn);

    $request_location_cn[1] = urldecode($request_location_cn[1]);

    $request_location = $request_location_loc[1];
    $request_call_number = strtoupper($request_location_cn[1]);

  }
}

$NOTSET = "RESERVE" ;  // constant: call number was not provided in the request
                  // default behavior appears to be QCAT -> no cn, equals a reserve item
                  // This can occur for any single item

// Get mapping file file handel to process the file
$config_file = dirname(__FILE__) . '/mapping.csv';

  $DEBUG_THINGS = false ; // flag to display debug info to browser window
  // $DEBUG_THINGS = true ; // flag to display debug info to browser window
  $line_length = 4096; // amount of chars per line to read from csv file

// CONSTANTS -------------------------------------------------------------------
  // CSV column offsets
  $LOCATION_CODE = 0 ;
  $LOCATION_LIBRARY = 1 ;
  $LOCATION_FLOOR = 2 ;
  $LOCATION_LABEL = 3 ;
  $FLOOR_OTHER = 4 ;
  $FLOORPLAN_URL = 5 ;
  $RANGE_START = 6 ;
  $RANGE_END = 7 ;
  // path to image files
  $PATHTOIMAGES = "https://library.queensu.ca/sites/default/files/styles/location_floor/public/images/floor-plans/" ;
  //$PATHTOIMAGES = "/sites/default/files/styles/location_floor/public/images/floor-plans/" ;
  // Image file names ----------------------------------------------------------
  // - CSV file contains hash ids to index image file name in floor plans images array
  // - add new hash codes here with coresponding file name
  $floor_plan_images = [
    "UARIS" => "stauffer_1st_level.png",
    "BLLL" => "bracken_lower_level_1.png",
    "BLIS" => "bracken_main_level_0.png",
    "BL1" => "bracken_main_level_0.png",
    "DL1" => "douglas_third_floor.png",
    "DL2" => "douglas_second_floor.png",
    "DL3" => "douglas_first_floor.png",
    "DLIS" => "douglas_fourth_floor.png",
    "DL4" => "douglas_fourth_floor.png",
    "DL5" => "douglas_fifth_floor.png",
    "DL6IS" => "douglas_6th_level.png",
    "DL6" => "douglas_6th_level.png",
    "DL7" => "douglas_seventh_floor.png",
    "EDIS" => "education_1st_level-2018.png",
    "ED1" => "education_1st_level-2018.png",
    "ED2" => "education_2nd_level.png",
    "ED3" => "education_3rd_level_0.png",
    "EDTRR" => "education_1st_level_trc_0.png",
    "LLIS" => "first_floor.png",
    "LL1" => "first_floor.png",
    "LL2" => "second_floor.png",
    "SLLL" => "stauffer_lower_level.png",
    "SLLLIS" => "stauffer_lower_level.png",
    "SL1IS" => "stauffer_1st_level.png",
    "SL1" => "stauffer_1st_level.png",
    "SL2" => "stauffer_2nd_level.png",
    "SL3" => "stauffer_3rd_level.png",
    "SL4" => "stauffer_4th_level.png"
  ] ;

//VARIABLES --------------------------------------------------------------------
  $item_location = "" ; // our output html
  $target_image = "" ; // the image information from CSV mapping file
  $callnumber = "" ; // stripped call number for testing if in range
  $old_callnumber = "" ; 
  $callnumberdigits = "" ; // call numbers can have digits; Douglas library ranges are define accross Letters and digits
                     // ie. A-QA272 on 2nd floor, QA273 - Z on 1st floor
                     // mapping file can have start and end ranges that support this
  $range_start = "" ; // mapping record start range
  $range_end = "" ; // mapping record end range

  $test_for_reserve = false ; // flag, testing for special cases (MICROLOG)
  $test_flag_start = false ; // flag, have we matched the range start of a record in our mapping file. true or false
  $test_flag_end = false ; // flag, have we matched the range end of a record in our mapping file. true or false
  $start_match_flag = false; // flag that we are or are not in the right record
  $end_match_flag = false; // flag that we are or are not in the right record

  $img_width = "70%" ; // used to style map img

  if ($DEBUG_THINGS) {
    echo "<br />loc: " . $request_location . "";
    echo "<br />cn: " . $request_call_number . "<br />";
  }

  // START SPECIAL CASES =========================================================
  // #0 Microform - Art collection microforms are now ll stauffer
  // adacosta - mar 2019
  // QUL Solutions 57601 - no call number and loc is ar,mf
  if ((strpos($request_location,"ar,mf") !== false ) && (($request_call_number == "") || (!isset($request_call_number)))) {
    $request_call_number = 'Microform Collection';
  }
  // #1 reserve items
  if (($request_call_number == "") || (!isset($request_call_number))) {
    // special case where QCAT id'd a reserve item and only send loc code
    // we strip first part of loc code and match location and RESERVE in csv column 4
    $request_call_number = $NOTSET ; // we may find a location code, but there is no matching floor plan (call number out of range, doesnt exist, etc)
    $request_location_temp_array = explode (",",$request_location) ;
    $request_location = trim($request_location_temp_array[0]); // take just the start of the loca code. ie sc,fol => sc
    $test_for_reserve = true ;
  }
  // #2 MICROLOG
  if (strpos($request_call_number,"MICROLOG") !== false) {
    $callnumber = "MICROLOG" ;
    $test_for_reserve = true ;
  }
  // END SPECIAL CASES ===========================================================

// START Processing
// if the mapping file exists
if(file_exists($config_file)) {

  // open map file and extract floor plans
  if (($fp = fopen($config_file, "r")) !== false ) {

    // strip the first 2 characters from the request (cn=), if not a reserve or special case item
    // Other call numbers may have MI
    // MICROLOG is an item that was handled as a special case in the previous apps database, mapped directly to
    // lower level Stauffer. We are now sending to IS Stauffer, 1st level (a "reserve" item)
    // Note: there may be other special cases. test may require is varaible in array of "special cases"
    //       special cases are hadled in the mapping file
    //
    if ($test_for_reserve == true) {
      // move on, we are dealing with a special case
      // See: START SPECIAL CASES above
    } else {
      // strip our call number to chars and digits,
      // call numbers are typically F#.<otherstuff> or FF#.<otherstuff>
      $temp_array = explode (".",$request_call_number) ; // explode call number into parts (delim by "."), we want 1st part
      $callnumber = trim(preg_replace("/\d+/", '', $temp_array[0])) ; // filter digits
      $old_callnumber = $callnumber; // record the query callnumber, incase we need to match query to single char mapping ranges
      $callnumberdigits = trim(preg_replace("/\D/", '', $temp_array[0])) ; // filter non decimal, range can be 4 digits
    }
    $line_counter = 0;

    // while there are rows in our mapping file, look for a match
    while (($data = fgetcsv($fp, $line_length)) !== FALSE) {
      $test_flag = false;

      $line_counter = $line_counter + 1;

      $range_start = trim(preg_replace("/\d+/", '', $data[$RANGE_START])) ; //
      $range_start_digits = trim(preg_replace("/\D/", '', $data[$RANGE_START])) ; // replace non digits, trim white space
      $range_end = trim(preg_replace("/\d+/", '', $data[$RANGE_END])) ; //
      $range_end_digits = trim(preg_replace("/\D/", '', $data[$RANGE_END])) ; // replace non digits, trim white space

      // have we found a mapping file location code that matches the QCAT loc code?
     if ($request_location == $data[$LOCATION_CODE]) {

       if ($DEBUG_THINGS) { // DEBUG
         echo "<br />call#: [" . $callnumber . "] [" . $callnumberdigits . "] " ;
         echo "_testing: " . $range_start . "(".$range_start_digits.")". " - " . $range_end ."(".$range_end_digits.")" . " [" .$request_location. "] @ row " . $line_counter . ": " . $data[$LOCATION_CODE];
       }

       // test if we are looking at a special case
      if ($test_for_reserve == true) { // if MICROLOG, special case, reserve item
        if (strtoupper($data[$FLOOR_OTHER]) == "RESERVE") {
          $test_flag = true ;
        } else {
          $test_flag = false ;
        }
      } // end if we are testing for reserve items MICROLOG
      else {

        // ADACOSTA TEST ====================================================================
        $callnumber = $old_callnumber ; // reset
        if ((strlen($callnumber) >= 1) && (strlen($range_start)) <= 1) {
          if ($DEBUG_THINGS) { echo "<br />START"." strcmp ". $callnumber[0] . " && ". $range_start . " (" . strcmp($callnumber[0], $range_start) . ")";}
          $start_match_flag = (strcmp($callnumber[0], $range_start) >= 0) ? true : false ;
        }
        else {
          if ($DEBUG_THINGS) { echo "<br />START"." strcmp ". $callnumber . " && ". $range_start . " (" . strcmp($callnumber, $range_start) . ")";}
          $start_match_flag = (strcmp($callnumber[0], $range_start) >= 0) ? true : false ;
        }
        if ((strlen($callnumber) >= 1) && (strlen($range_end)) <= 1) {
          if ($DEBUG_THINGS) { echo "<br />END"." strcmp ". $callnumber[0] . " && ". $range_end . " (" . strcmp($callnumber[0], $range_end) . ")";}
          $end_match_flag = (strcmp($callnumber[0], $range_end) <= 0) ? true : false ;
        }
        else {
          if ($DEBUG_THINGS) { echo "<br />END"." strcmp ". $callnumber . " && ". $range_end . " (" . strcmp($callnumber, $range_end) . ")";}
          $end_match_flag = (strcmp($callnumber, $range_end) <= 0) ? true : false ;
        }
        // ADACOSTA end TEST ================================================================
        // If call number in in a mapping file record's range values
        if (($callnumber >= $range_start) && ($callnumber <= $range_end)) {

          if ($DEBUG_THINGS) { // DEBUG
            echo "<br /> >>>> " . $callnumber . " is in range " . $range_start ." to ". $range_end 
                . " (" . $start_match_flag . " / " .$end_match_flag . ")";
          }
          $test_flag = true ; // we are in a mapping record that matches call number ranges
                              // eg. call number F is in range A to H
                              // ?loc=sl&cn=F against record "sl,...,A,H,"

          if ((isset($callnumberdigits)) && ($callnumber == $range_end) && (isset($range_end_digits)) && ($range_end_digits !=='')) { // need to test call number's digits against end range digits
            // if we are testing end range number
            $test_flag = ($callnumberdigits <= $range_end_digits) ? true : false ;  // if the call number digits <= range end digits we are in the right record - true
                                                                                      // if the call number difits are > than range end digitd, move on - false
            if ($DEBUG_THINGS) { // DEBUG
              $retest_flag = ($callnumberdigits <= $range_end_digits) ? "true" : "false" ;
              echo  "<br />" ."testing end range" 
                  . ": " . $callnumber . $callnumberdigits 
                  . " versus " . $range_end . $range_end_digits
                  . " _ " . $retest_flag;
            }
          }
        } // end if we have call number in range
      } // end else of if test for reserve

        // if we still have a true condition (call number is within a mapping record's start and end ranges),
        // we have found a matching record in our mapping file
        // or cn was empty or not found (make sure we actually have a match)

        if ($test_flag == true) {

          if ($DEBUG_THINGS) { // DEBUG
            echo "<br /> <hr>" ;
            echo "ITEM <strong>". $callnumber ."</strong> located " . " at <strong>" . $data[$LOCATION_LIBRARY] . "</strong> on " .$data[$LOCATION_FLOOR] ." (".$data[$LOCATION_LABEL].")";
            echo "<br />" .$data[$FLOORPLAN_URL];
            echo "<br />" . $image_from_array_lookup = $PATHTOIMAGES . $floor_plan_images[$data[$FLOORPLAN_URL]] ;
            echo "<br />using code " .$data[$LOCATION_CODE]. " at line " . $line_counter;
            echo "<br /> <hr>" ;
          }
          // set our output html, location description and image src and alt text
          $alt_text = $data[$LOCATION_LIBRARY]. " library floor plan, " .$data[$LOCATION_FLOOR] ;
          // set the image html
          $image_from_array_lookup = $PATHTOIMAGES . $floor_plan_images[$data[$FLOORPLAN_URL]] ;
          //$target_image = "<img src=\"".$image_from_array_lookup."\" alt=\"".$alt_text."\" width=\"95%\" height=\"95%\">" ;
          $target_image = "<img src=\"".$image_from_array_lookup."\" alt=\"".$alt_text."\">" ;
          // build the html to display the matching record's floor plan location information and associated image
          $item_location = "<h1>" . $data[$LOCATION_LIBRARY] . " Library</h1>"
                     . "<h2>" . $data[$LOCATION_FLOOR] . ", " .$data[$LOCATION_LABEL] . "</h2>"
                     . "<p>"
                     . "Item: <strong>" . $request_call_number . "</strong>"
                     . "</p>";
          $item_location .= $target_image ;
          // we can stop here
          break ;
        } // end if we have a matching record

        if ($DEBUG_THINGS){ // DEBUG
          echo "<hr>";
          echo "</p>" ;
        }
     } // end if we have a matching loc code
     else { // we do not have a loc code

       // display a default no image found message
       $item_location = "<p><strong>A floor plan location is not available for this item.</strong>"
                  . "<br />" . "Call Number: " . $request_call_number
                  . "<br />" . "Please contact Information Services.</p>" ;

     } // end else we do not have a loc code

   } // END LOOP

  } // end if we opened file

  // we are done close the mapping file
  fclose($fp);

  if ((isset($using_pipe)) && ($using_pipe == true)) {
    $img_width = "90%";
    if ($DEBUG_THINGS) { echo "<br />we are using the pipe and flag is true, width is 50%" ;}
  } else {
    if ($DEBUG_THINGS) { echo "<br />we are using the pipe and flag is false, width is 100%" ;}
  }
} // end if file exists
// STOP Processing
?>

<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link rel="shortcut icon" href="https://library.queensu.ca/sites/all/themes/qul/favicon.ico" type="image/vnd.microsoft.icon">

        <title>OMNI - Find on Floorplan</title>

        <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet">

        <style>
          body {
            font-family: "Open Sans",Helvetica,Arial,sans-serif;
            font-size: 14px;
            width: 600px;
            height: 900px;
          }
          img {
            width: <?php echo $img_width; ?>
          }
          h1, h2 {
            color: #00305e;
            font-weight: 300;
            margin-bottom: -10px;
          }
          h1 {
            font-size: 20px;
          }
          h2 {
            font-size: 16px;
          }
        </style>

    </head>
    <body>
        <div>
          <?php
            echo $item_location ;
          ?>
        </div>
    </body>
</html>
