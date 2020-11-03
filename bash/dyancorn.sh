#!/bin/bash
#Download Dynacorn data file
echo "Downloading Dynacorn feed file..." ;
/usr/local/bin/wp rp download_dynacorn --path=$1 ;

#Process Dynacorn data file
echo "Processing Dynacorn feed file..." ;
/usr/local/bin/wp rp process_dynacorn --path=$1 ;

echo "Process completed" ;