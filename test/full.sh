#!/bin/bash

set -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
# shellcheck disable=SC1091
source "$DIR/../pcon/test/common.sh"

clear
echodiv
echo "Running full test suite with all configured PHP versions"

function full_test {
    php_version="$1"
    pced "Running full test suite with PHP $php_version"
    echo "Authenticate to switch CLI to PHP $php_version"
    sudo update-alternatives --set php "/usr/bin/php$php_version"
    echo "Using PSSH from $(which pssh)"
    pssh version --verbose
}

full_test "8.2"
full_test "8.1"
full_test "8.0"
full_test "7.4"

# Reset to latest PHP version
sudo update-alternatives --set php "/usr/bin/php8.2"
