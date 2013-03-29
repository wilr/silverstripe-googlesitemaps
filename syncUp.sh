#/bin/sh
dir=${PWD##*/}
/usr/bin/rsync -carvut -e 'ssh' --progress --stats --exclude-from=not_rsync_up --delete \
./* \
/home/g4b0/Develop/SilverStripeModules/$dir
