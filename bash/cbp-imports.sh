#!/bin/bash

#run oer import
echo "Running OER import..." ;
/usr/local/bin/wp all-import run 127 --path=$1 ;
echo "OER import complete..." ;

#run dii import 
echo "Running DII import..." ;
/usr/local/bin/wp all-import run 130 --path=$1 ;
echo "DII import complete..." ;

echo "Imports completed!" ;