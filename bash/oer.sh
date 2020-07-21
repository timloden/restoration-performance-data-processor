#!/bin/bash

export $(grep -v '^#' .env | xargs)

#Download OER data file
echo "Downloading OER feed file..." ;
wp rp download_oer --path=$WP_PATH ;

#Process OER data file
echo "Processing OER feed file..." ;
wp rp process_oer --path=$WP_PATH ;

echo "Process completed" ;