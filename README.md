# Getting Started

## Install PSSH
1. Make sure you have PHP
2. Download main executable
3. Move/symlink into path or set up alias
4. Re-download as needed to update

## Import Existing Config
1. Import your current config to a new personal JSON file

        pssh import ~/.pssh/ssh_config_personal.json

2. Sync up your work config, or manually separate imported json into work & personal files. See generated ~/.pssh/config for all relevant default paths. If using a private git repository to sync up, you can simply fill in the ssh (git@) URL into ~/.pssh/config - as the value for 'sync'.  Syncing will run before and after adding a new host automatically if configured.  It can also be run with:

        pssh sync

3. If syncing existing work file, the merge command will help you merge your imported json into your synced work json file.  Diff the result to make sure you don't sync something you wanted to keep private!

        pssh merge ~/.pssh/ssh_config_personal.json ~/.pssh/ssh_config_work.json

4. Sync again when ready

        pssh sync

# General Usage

# Config File Details
