#!/bin/bash
# Unzip directory of multiple .zip folders into respective folders
# Expected input: directory of .zip folders in sequence:
# e.g. folder1.zip, .. ,foldern.zip)
# Output: Associated folders folder1, .. ,foldern in current directory
# Explanation of problem at bottom for the curious

# Index for creating folders 1..n
index=0;

# Find all zip folders in the current directory, sort them and iterate through them
find *.zip -maxdepth 1 -type f | sort | while IFS= read -r file; do

	#flag to check if file has been moved
	moved=0;

	# increment index
	((index++))

	#create new folder
	TARGET="./zip$index"
    mkdir -p "$TARGET"	

	# The moved will be 0 until the file is moved
    while [ $moved -eq 0 ]; do
    	
    	# If the directory has no files
		if find "$TARGET" -maxdepth 0 -empty | read; 
		then 
		  # Extract the current file to $target and increment the moved
		  unzip "$file" -d "$TARGET" && moved=1; 
		else
		  # Uncomment the line below for debugging 
		  # echo "Directory not empty: $(find "$target" -mindepth 1)"

		  # Wait for one second. This avoids spamming 
		  # the system with multiple requests.
		  sleep 1; 
		fi;
    done;
done

echo -e "\nExtract completed..\n"
exit 0

# EXPLANATION OF PROBLEM
# By default .zip folder with only 1 file extracts to current directory,
# such that unzip '*.zip' orphans single files with no folder context
# e.g. currentdir/folder1.zip/sample1.jpg, .. ,/folder2.zip/sample2.jpg, sample3.jpg
# extracts to currentdir/sample1.jpg, .. ,folder2/sample2.jpg, sample3.jpg
# Desired: currentdir/folder1/sample1.jpg, .. ,folder2/sample2.jpg, sample3.jpg











