#!/bin/bash

#Download exsisting Goodmark data file
echo "Downloading exsisting Goodmark feed file..." ;
/usr/local/bin/wp rp download_existing_goodmark --path=$1 ;

#Download Goodmark data file
echo "Downloading Goodmark feed file..." ;
/usr/local/bin/wp rp download_goodmark --path=$1 ;

#Process Goodmark data file
echo "Processing Goodmark feed file..." ;
/usr/local/bin/wp rp process_goodmark --path=$1 ;

echo "Process completed" ;