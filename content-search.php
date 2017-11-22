<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html>
    <head>
        <title>Data Analyatics</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            .highlight
            {font-weight: bold;font-size:24px;text-decoration:underline;}
            body{font-size:16px;font-family: monospace,arial,sans-serif;}
            .container{width:100%;height:400px;overflow-x: auto;border:1px solid #ccc;border-radius: 4px;padding:15px;line-height:40px;}
        </style>
    </head>
    <p style="">
        <?php
        include_once 'csv.php';


        $sText = file_get_contents('newscode.txt');
        $text = strtolower($sText);

        $searchfor = $aCategory;

        $result = customHighlights($sText, $searchfor);
        ?>
    <h2>CONTENT : >>  </h2>
    <div class="container">

        <?php  print $result; ?>
    </div>
    <?php
    $patterns = array();
    foreach ($searchfor as $key => $val) {
        $value = strtolower($val);
        $patterns[$key] = "/^.*$value.*\$/m";
    }
    $slno = 1;
    echo "<h3>FOUND MATCHES : >> </h3>";
    foreach ($patterns as $k => $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            $arr[] = array(
                'cat_id' => $aTagCategory[$k]['category'],
                'cat_name' => $aTagCategory[$k]['category_name'],
                'text_count' => substr_count($text, strtolower(substr($pattern, 4, -5)))
            );
        }
        $slno++;
    }
    ?>

  <?php

	$new_arr = array_map(function($k, $pattern) use ($aTagCategory, $text) {
    if (preg_match_all($pattern, $text)) 
	{
		
		//echo implode("\n", $matches[0]);
		echo "<b>Category : </b>" . $aTagCategory[$k]['category'] . " (".$aTagCategory[$k]['category_name'].") <b> Dependent Text : </b>" . substr($pattern, 4, -5) ." = ".substr_count($text, strtolower(substr($pattern, 4, -5))).",<br>";

			return array(
					'cat_id' => $aTagCategory[$k]['category'],
					'cat_name' => $aTagCategory[$k]['category_name'],
					'text_count' => substr_count($text, strtolower(substr($pattern, 4, -5)))
				);
			return $arr;
		} else {
		// echo "No matches found";
		
	}

}, array_keys($patterns), $patterns);

$sum = array_reduce($new_arr, function ($a, $b) {
			isset($a[$b['cat_id']]) ? $a[$b['cat_id']]['text_count'] += $b['text_count'] : $a[$b['cat_id']] = $b;  
			return $a;
		});
echo "<br/><hr>";
usort($sum, function($a, $b) {
			return $b['text_count'] - $a['text_count'];
		});
foreach($sum as $keys => $val)
{
	if($val != "")
	{
		echo "category name = ".$val['cat_name'].", total= ".$val['text_count']."<br>";
	}
	
}
		
//$new_arr = array_filter($new_arr, function($value) { return strlen($value)> 0; });
//print_r($new_arr);

//$counted = array_count_values($new_arr);
// The item_id
//print_r($counted);
		
?>

</body>
</html>
