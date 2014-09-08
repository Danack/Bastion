<?php

//@todo make it look more like 
//http://www.imagemagick.org/download/linux/CentOS/x86_64/


function writeHTMLFiles($directory, $files) {

    echo "Processing ".$directory."\n";
    
    $excludeFiles = [
        ".DS_Store"
    ];
    
    $contents = <<< END
<html>
<body>


END;

    foreach ($files as $file) {
        if ($file === "index.html") {
            continue;
        }
        if (in_array($file, $excludeFiles) == true) {
            continue;
        }
        
        $file = htmlentities($file);
        $contents .= "<a href='$file'>$file</a><br/>\n";
    }
    
    $contents .= <<< END
</body>
</html>

END;

    file_put_contents($directory."/index.html", $contents);
}


function generateHTMLForDirectory($directory, $root = true) {

    $files = [];

    $directoryIterator = new DirectoryIterator($directory);

    foreach ($directoryIterator as $item) {
        echo $item->getFilename();

        echo "\n";

        if ($item->isDir()) {
            if ($item->isDot() == false) {
                generateHTMLForDirectory($item->getRealPath(), false);
            }
        }
        
        if ($root == false || $item->isDot() == false) {
            $files[] = $item->getFilename();
        }
    }

    writeHTMLFiles($directory, $files);
}

generateHTMLForDirectory('../repo');
