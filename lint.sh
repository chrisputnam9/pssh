#!/bin/bash

clear

cp -f pssh pssh-load.php

phpcs && rm pssh-load.php && exit 0

echo "==========================================="
echo "THERE HAS BEEN AN ISSUE - RESOLVE AND RERUN"
echo "==========================================="
exit 1
