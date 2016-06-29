#!/bin/bash

#########################
#
# Find duplicate exception timestamps and list them
#
# Use within TYPO3 CMS source
#
#
# The script searches for duplicate timestamps with
# two exceptions:
# 1. timestamps defined by the "IGNORE" array
# 2. timestamps within Tests directories
#
#
# @author  Christoph Kratz <ckr@rtp.ch>
# @author  Christian Kuhn <lolli@schwarzbu.ch>
# @date 2016-04-18
#
##########################

# Array of timestamps which are allowed to be non-unique
IGNORE=("1270853884")

# Respect only php files and ignore files within a "Tests" directory
DUPLICATES=$(grep -r --include \*.php --exclude-dir Tests 'throw new' -A5 typo3/ | grep '[[:digit:]]\{10\}' | awk '{
    for(i=1; i<=NF; i++) {
        if(match($i, /[0-9]{10}/)) {
            print $i
        }
    }
}' | cut -d';' -f1 | tr -cd '0-9\012' | sort | uniq -d)

COUNTER=0

for CODE in ${DUPLICATES}; do
    # Ignore timestamps which are defined by the "IGNORE" array
    if [ ${IGNORE[@]} != ${CODE} ] ; then
        echo "Possible duplicate exception code ${CODE}: grep -r --include \*.php --exclude-dir Tests ${CODE} typo3/"
        COUNTER=$((COUNTER+1))
    fi

done

if [ ${COUNTER} -gt 0 ] ; then
    echo "${COUNTER} possible duplicate exception codes found."
    exit 1
fi

exit 0

