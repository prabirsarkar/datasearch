<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html>
    <head>
        <title>TODO supply a title</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>TODO write content</div>
        
      <?php  /*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$text = "Bike accident, daru khoa hoi,juya khela hoi,cholai mod bikri , mod bikri kore , area peacefull , khoya,বিনা হেলমেটে তিন জন।";

$array = array('when', 'choice');
$find = "Islamic jalsha";

//echo substr_count(strtolower($text),'When you create pictures');




similar_text($text, $find, $percent);
//echo $percent;

$file = 'somefile.txt';
$searchfor = array("khoya","accident","bike","prabir","বিনা হেলমেটে তিন জন।");

//$searchfor = $imp = implode("/", $exp);
// the following line prevents the browser from parsing this as HTML.
//header('Content-Type: text/plain');
// get the file contents, assuming the file to be readable (and exist)
$contents = $text; //file_get_contents($file);
// escape special characters in the query
//$pattern = preg_quote($searchfor, '/');
//$pattern1 = preg_quote("accident", '/');
// finalise the regular expression, matching the whole line
//$pattern = "/^.*$pattern.*\$/m";



$patterns = array();
foreach($searchfor as $key=>$val)
{
    $patterns[$key] = "/^.*$val.*\$/m";
}

$slno = 1;
foreach ($patterns as $pattern) {
    // search, and store all matching occurences in $matches
   // echo $slno."\n";
    if (preg_match_all($pattern, $contents, $matches)) {
        echo "\nFound matches:\n";
        //echo implode("\n", $matches[0]);
        echo $pattern;
    } else {
       // echo "No matches found";
    }
    $slno++;
}
     ?>
    </body>
</html>
