# Release Steps for PSSH
1. Follow [release steps for PCon](https://github.com/chrisputnam9/pcon/blob/master/README-RELEASE-STEPS.md) first if supporting changes made there
1. Compare and maybe pull updates from PCon:
    - [doc.sh](./doc.sh)
    - [lint.sh]](./lint.sh)
    - [phpdoc.xml](./phpdoc.xml)
1. Increment version in [README.md](./README.md) and [src/pssh.php](./src/pssh.php)
1. Run `./test/full.sh` and review output for issues
1. Run `./lint.sh` and fix any issues
1. Run `./doc.sh`
1. Run `pcon package pssh`
1. Run `./test/full.sh packaged` and review output for issues
1. Merge & push to master
1. Run `sudo pssh update` and confirm on latest version (may need to wait for cash at times)
1. Run `./test/full.sh installed` and review output for issues
