#!/bin/bash
echo "Running functional test suite ${1}"
echo ${PARALLEL_SEQ}
./bin/phpunit --colors -c typo3/sysext/core/Build/FunctionalTests.xml ${1}
DB=`cat /tmp/${PARALLEL_SEQ}`
mysql -u ${typo3DatabaseUsername} --password=${typo3DatabasePassword} --socket=${typo3DatabaseSocket} -e "DROP DATABASE ${DB}"
