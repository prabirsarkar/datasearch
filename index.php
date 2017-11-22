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
            {font-weight: bold;font-size:24px;}
            body{font-size:16px;font-family: monospace,arial,sans-serif;}
        </style>
    </head>
    <p style="">
        <?php
        include_once 'csv.php';
        /*
         * To change this license header, choose License Headers in Project Properties.
         * To change this template file, choose Tools | Templates
         * and open the template in the editor.
         */
        $sText = " bike accident On the Insert tab, the galleries include items that are designed to coordinate with the overall look of your document. You can use these galleries to insert tables, headers, footers, lists, cover pages, and other document building blocks. When you create pictures, charts, or diagrams, they also coordinate with your current document look.
You can easily change the formatting of selected text in the document text by choosing a look for the selected text from the Quick Styles gallery on the Home tab. You can also format text directly by using the other controls on the Home tab. Most controls offer a choice of using the look from the current theme or using a format that you specify directly.Kal kalchini Daramsala mi BJP ka meeting honai wala hai jismai BJP state president upastith hongai sir.Sir Gokulnagar swafala jn high school ar persona Harkanto barman ar bareta Thakur puja upolokha vaoia gan haba Ajka 9 PM thaka , and 2 wheeler churi kore paliye geche
To change the overall look of your document, choose new Theme elements on the Page Layout tab. To change the looks available in the Quick Style gallery, use the Change Current Quick Style Set command. Both the Themes gallery and the Quick Styles gallery provide reset commands so that you can always restore the look of your document to the original contained in your current template always late.

";


        $text = strtolower($sText);
        $searchfor = $aCategory;

        $result = customHighlights($sText, $searchfor);
        //print $result;

        $patterns = array();
        foreach ($searchfor as $key => $val) {
            $value = strtolower($val);
            $patterns[$key] = "/^.*$value.*\$/m";
        }

        $slno = 1;
        echo "<h4>Found matches:</h4>";
        foreach ($patterns as $k => $pattern) {
            // search, and store all matching occurences in $matches
            if (preg_match_all($pattern, $text, $matches)) {
                //echo implode("\n", $matches[0]);
                echo "<b>Category : </b>" . $aTagCategory[$k]['category'] . " <b> Dependent Text : </b>" . substr($pattern, 4, -5) . ",<br>";

                //print_r($matches);
            } else {
                // echo "No matches found";
            }
            $slno++;
        }
        ?>
    </p>
</body>
</html>
