#!/bin/bash

#Download OER data file
echo "Downloading OER feed file..." ;
wp rp download_oer ;

#Process OER data file
echo "Processing OER feed file..." ;
wp rp process_oer ;

echo "Process completed" ;