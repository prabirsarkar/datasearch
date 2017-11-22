

<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$row = 1;
$slno = 0;
$aCategory = array();
$aTagCategory = array();
if (($handle = fopen("category.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        //echo "<p> $num fields in line $row: <br /></p>\n";
        $row++;
        for ($c = 0; $c < $num; $c++) {
            //echo $data[$c] . "<br />\n";
        }

        $aCategory[$slno] = strtolower($data[2]);
        $aTagCategory[$slno]['category'] = $data[0];
		$aTagCategory[$slno]['category_name'] = $data[1];
        $aTagCategory[$slno]['description'] = $data[2];
        $slno++;
    }
    fclose($handle);
}

//echo "<pre>";

/*
print_r($aCategory);
print_r($aTagCategory);




$searchString = "Bike accident The car is driving in the carpark, he's not holding to the right lane.puja , accident \n";
$toHighlight = $aCategory;

$result = customHighlights($searchString,$toHighlight);

print $result;
*/


// add the regEx to each word, this way you can adapt it without having to correct it everywhere

function addRegEx($word){
    return "/" . $word . '[^ ,\,,.,?,\.]*/i';
}

function highlight($word){
    return "<span class='highlight'>$word[0]</span>";
}

function customHighlights($searchString,$toHighlight){
// define your word list
$searchFor = array_map('addRegEx',$toHighlight);
$result = preg_replace_callback($searchFor,'highlight',$searchString);
return $result;
}
