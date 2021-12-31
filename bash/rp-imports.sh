#!/bin/bash
#this script is added to root and not used via plugin

#run oer import
echo "Running OER import..." ;
/usr/local/bin/wp all-import run 172 --path=$1 ;
echo "OER import complete..." ;

#run dii import 
echo "Running Goodmark import..." ;
/usr/local/bin/wp all-import run 83 --path=$1 ;
echo "Goodmark import complete..." ;

echo "Imports completed!" ;