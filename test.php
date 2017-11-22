<html>
    <head>
        <title>TMS</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>

        <?php
        $con = mysqli_connect('localhost', 'root', '', 'tms2017_live');
        mysqli_query($con, "set character_set_results='utf8' ");
        mysqli_query($con, "SET comment collation_connection ='utf8_general_ci' ");


        $file = "newscode.txt";
        $f = fopen($file, 'w'); // Open in write mode
        $sql = mysqli_query($con, "SELECT CONCAT( headline, ', ',comment ) AS concats FROM `tms_news` WHERE `news_added_time` >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)"); //data of last 90 days 
        while ($row = mysqli_fetch_array($sql)) {
            $infromation = $row['concats'];
            $news = "$infromation\n";
            fwrite($f, $news);
        }
        fclose($f);
        echo "<a href=newscode.txt>Click here to see the texts!</a>";
        ?>

    </body>
</html>



