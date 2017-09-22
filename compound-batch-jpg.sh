#!/bin/bash

# NOTE: Find & Replace .jpg w/ .tif when necessary

# Partner script with batch-jpg.sh for multiple books/manuscripts

# Index for creating folders 1..n
index=0; 


# Create folder one level up for pages
FOLDER="../book"
mkdir -p "$FOLDER"

# Skips over .sh scripts in directory (replace 'zip' with folder prefix)
for i in ./zip* ; do
	
	# increment index
	((index++)) 

	manuscript=page$index;
	
	# Syntax: script.sh ./directory bookName
	# Manuscript named 'page' specifically for Scrapbook project
	./batch-jpg.sh "$i" "$manuscript"; 

	# Move to external folder; throws an error but works?
	find zip* -name "page*" -exec mv {} ../book \;

	# Replace w/ following if you want a directory of zipped folders
	# Uncomment below and remove 'Delete .zip' section
	# find zip* -name "*.zip" -exec mv {} ../book \;
	
done

	# Compression final step in batch-jpg.sh but we aren't there yet
	# Delete .zip folders in book
	cd ../book
	
	find . -name "*.zip" -exec rm {} \;

	# NOW we compress everything.
	# All together now!
	zip -r $FOLDER.zip $FOLDER
	echo "Archive completed..\n"
	echo "Done.\n"
	exit 0