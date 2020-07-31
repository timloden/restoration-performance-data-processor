#!/bin/bash

#Download OER data file
echo "Downloading OER feed file..." ;
/usr/local/bin/wp rp download_oer --path=$1 ;

#Process OER data file
echo "Processing OER feed file..." ;
/usr/local/bin/wp rp process_oer --path=$1 ;

echo "Process completed" ;