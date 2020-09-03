#!/bin/bash

#Download RPUI data file
echo "Downloading RPUI feed file..." ;
/usr/local/bin/wp rp download_rpui --path=$1 ;

#Process RPUI data file
echo "Processing RPUI feed file..." ;
/usr/local/bin/wp rp process_rpui --path=$1 ;

echo "Process completed" ;