# find-on-floorplan

## Description

OCTOBER 24, 2019 - REWRITING TO INTEGRATE WITH PrimoVE

In progress

This application replaces the existing *Find on floor plan* application developed  provides floor plan locations for items in QCAT.

QCAT search results for items that are physically located at Queen's Library display a *Show on floorplan* link. Clicking this link presents the user with a popup window the location of the item on a library floor plan.

The existing php web application relies on a relational database that describes, libraries, floors, sections and locations. This system is difficult to maintain, requiring editing of image files separate from those used for the web site, calculating pixel x,y offsets to display the bouncing boxes, and manual changes to multiple database records.

The new web application proposes a simpler approach. Displaying a floor plan image with text describing the location of the item. This solution leverages the same floor plan images displayed on the website and uses a single csv file to determine item locations. The CSV file describes QCAT codes, location descriptions, floor plan images, and stack ranges. The only change required in QCAT is to target a new web server address.

## Files

### index.php

Extracts location and call number from URL path, searches CSV file to map location and call number to a specific floor plan image. If a matching record is found, the image and location description is displayed to the user. If a floor plan image is not found, a message is displayed indicating that there is no matching floor plan for the item and the user is prompted to contact Information Services.

- Path to floor plan images is defined here.
- Floor plan image file names are defined here.
- Path and name to CSV look up file is defined here.

Output is an HTML page with the results of the search.

### mapping.csv

CSV file containing QCAT location codes, call number ranges and related floor plan images.

#### Columns
##### location code
Location codes are as defined in QCAT.

##### library name
Name of the library where the item is to be found. For display purposes only.

##### location description
Descriptive text that describes where on the floor plan the item is located. For display purposes only.

##### holding description
Description of the item catalog. As defined in QCAT. Edited for clarity when required. For display purposes only.

##### special case
Special column to handle edge case reserve items, or call number ranges that break general rules.

For example:
- call number starts with *MICROLOG*
- no call number sent from QCAT (reserve item)

##### floor plan image code
Codes used to look up the actual image file name to display to user. Codes are defined in the *index.php file*.

##### range start
Start of a location range. Collections can be located across multiple floors/locations in a library. An item is identified as belonging to specific location by falling within a start and end range.

##### range end
End of a location range. Collections can be located across multiple floors/locations in a library. An item is identified as belonging to specific location by falling within a start and end range.

##### record notes
Included for documentation purposes only

#### Example Rows
"es","Douglas","1st Floor (4S)","Engineering & Science Library - Books","DL1","QA273","ZA4080","EngSci Books"

"sl","Stauffer","Information Services","Reserve Item","RESERVE","SL1IS","A","Z","Special Case - QCAT did not send a call number"

## Notes

### Start and End ranges

  Complete ranges are expressed in two columns. A range start and range end. Start and end ranges values can be one or two letters, with or without number values.

  For example:
  - A,CZ
  - D,QA271
  - QA272,ZA4080

  Call numbers include letters and numbers. And look something like `PR8923 W6 L36 1990 c.3`.  We are only interested in the first part, and separate the leading letters and numbers. If a CSV record has a start or and range that includes numbers, then both parts of the call number are compared to the appropriate parts of the range values. If the range values do not have number components, then only the letters are compared.

### Special cases

  There are several special cases accounted for in code and in the CSV file.

  1. MICROLOG

  Records that have a call number starting with *MICROLOG* are historically located in Stauffer lower level in the microform section. Location code do,mclg.

  2. RESERVE items

  Any individual holding in QCAT can be catalogued as a reserve item. In this case, code in QCAT (hold over from old application) tests for "reserve" and sends only the location code. We test for missing call numbers and if so, we strip any extended location details, and we look for a RESERVE record in the CSV file matching the location code.

  CSV records are required for each location code series (such as es, ll, ir), that has Information Services targeting details.

  For example:
    From QCAT we recieve ?loc=ir,do
    We process ?loc=ir and search the CSV file for "ir" and "RESERVE"
    "ir","Stauffer","Information Services","Reserve Item","RESERVE","SL1IS","A","Z"
