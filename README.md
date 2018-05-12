# Getting Started

## Install PSSH
1. Make sure you have PHP
2. Download and make pssh executable

    

3. Move/symlink into path or set up alias



4. Re-download as needed to update

## Import Existing Config
1. Import your current config to a new personal JSON file

        pssh import

2. Sync up your work config, or manually separate imported json into work & personal files. See generated ~/.pssh/config for all relevant default paths. If using a private git repository to sync up, you can simply fill in the ssh (git@) URL into ~/.pssh/config - as the value for 'sync'.  Syncing will run before and after adding a new host automatically if configured.  It can also be run with:

        pssh sync

3. If syncing existing work file, the merge command will help you merge your imported json into your synced work json file.

        pssh merge ~/.pssh/ssh_config_imported.json ~/.pssh/ssh_config_work.json ~/.pssh/ssh_config_personal.json

4. Review the personal file - conflicts will be placed here, some may require manual adjustments.
   See comment in pssh section for each host.

5. Diff the work file to make sure you don't sync something you wanted to keep private!

        git difftool

4. Sync again when ready

        pssh sync

# General Usage

# Config File Details
