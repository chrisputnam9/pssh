#!/bin/bash

clear

cp -f pssh pssh.php

phpcs && rm pssh.php && exit 0

echo "==========================================="
echo "THERE HAS BEEN AN ISSUE - RESOLVE AND RERUN"
echo "==========================================="
exit 1
