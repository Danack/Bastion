<?php


namespace Bastion;


interface Uploader {
    function putFile($sourceFile, $destFile);
    function syncDirectory($srcDirectory, $destDirectory);
    function finishProcessing();
}
 