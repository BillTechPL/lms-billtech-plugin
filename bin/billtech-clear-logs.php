#!/usr/bin/env php
<?php

    $logDirectory = '/var/log/billtech/';

    $fileSystemIterator = new FilesystemIterator($logDirectory);
    foreach ($fileSystemIterator as $file) {
        if (time() - $file->getCTime() >= 60*60*24*60){
            unlink($logDirectory.$file->getFilename());
        }
    }
