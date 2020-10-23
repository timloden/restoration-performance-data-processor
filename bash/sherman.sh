#!/bin/bash

#Download OER data file
echo "Downloading Sherman feed file..." ;
/usr/local/bin/wp rp download_sherman --path=$1 ;

#Process OER data file
echo "Processing Sherman feed file..." ;
/usr/local/bin/wp rp process_sherman --path=$1 ;

echo "Process completed" ;