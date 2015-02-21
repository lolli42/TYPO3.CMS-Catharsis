#!/bin/bash
echo "Running functional test suite {}"
echo ${PARALLEL_SEQ}
ls -lR /dev/shm/typo3ramdisk/
./bin/phpunit --colors -c typo3/sysext/core/Build/FunctionalTests.xml ${1}
ls -lR /dev/shm/typo3ramdisk/
DB=`cat /tmp/${PARALLEL_SEQ}`
mysql -u ${typo3DatabaseUsername} --password=${typo3DatabasePassword} --socket=${typo3DatabaseSocket} -e "DROP DATABASE ${DB}"
ls -lR /dev/shm/typo3ramdisk/
