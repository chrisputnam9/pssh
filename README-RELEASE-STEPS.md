1. Increment version in README.md and src/pssh.php
2. Compare and maybe pull updates from pcon:
    - doc.sh
    - lint.sh
    - phpdoc.xml
3. Run ./test/full.sh and review output for issues
4. Run ./lint.sh and fix any issues
5. Run ./doc.sh
6. Run `pcon package pssh`
7. Switch alias to packaged version & rerun ./test/full.sh
9. Merge & push to master
10. Switch alias back to use installed version
11. Run `sudo pssh update`
12. Rerun ./test/full.sh now using packaged & installed latest version
