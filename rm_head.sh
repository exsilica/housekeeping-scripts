#!/bin/bash

#### Last updated: 2018-01-11 12:25 EDT

#### General purpose: Mass-edit filenames across directories
#### Adjust as needed
####
#### Specific case: Remove VCL_Scrapbooks_ head from filenames

for d in ./*/; do 
    cd "$d" &&
    for filename in *; do
        [ -f "$filename" ] || continue
        mv "$filename" "${filename//VCL_Scrapbooks_/}"
done
    cd ..
done

#### Does not run immediately
#### Only executes after traceback: bash -x rm_head.sh
#### Why?