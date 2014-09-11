#!/bin/sh
#
# TODO: Falls chmod versucht, per Netzlaufwerk eingebundene Dateien zu bearbeiten, schlägt es fehl (read-only file system).
#
PROJECTS_ROOT=/var/www
PROJECT=pewasoft.myfx


# update dependencies
REQUIRED_PROJECT=/var/www/php-lib
[ -f $REQUIRED_PROJECT/bin/cvs-update.sh ] && $REQUIRED_PROJECT/bin/cvs-update.sh


# update project
echo Updating $PROJECT ...

PASS=`cat ~/.cvs.shadow`

cd $PROJECTS_ROOT

export CVSROOT=:pserver:$USERNAME:$PASS@localhost:2401/var/cvs/pewa

cvs -d $CVSROOT login
#-------------------------------------------------------------
#cvs -d $CVSROOT -qr checkout -PR -d $PROJECT -r HEAD $PROJECT
cvs -d $CVSROOT -qr update -CPRd -r HEAD $PROJECT
#-------------------------------------------------------------
cvs -d $CVSROOT logout
export -n CVSROOT


# update file modes (may take some time, let's do it in the background)
find $PROJECT/conf -follow -type f                                                               -print0 2>/dev/null | xargs -0r chmod 0644          && \
find $PROJECT      -follow -type d \( ! -group apache -o ! -user apache \) ! -name 'CVS'         -print0 2>/dev/null | xargs -0r chown apache:apache && \
find $PROJECT      -follow -type d                                                               -print0 2>/dev/null | xargs -0r chmod 0755          && \
find $PROJECT      -follow -type f   -path '*/bin*' -prune -regex '.*\.\(pl\|php\|sh\)'          -print0 2>/dev/null | xargs -0r chmod 0754          && \
find $PROJECT      -follow -type f ! -path '*/bin*' -prune ! -path '*/tmp*' -prune ! -perm 0644  -print0 2>/dev/null | xargs -0r chmod 0644          && \
find $PROJECT      -follow \( -name '.ht*' -o -name '*_log' \)                                   -print0 2>/dev/null | xargs -0r chown apache:apache && \
find $PROJECT      -follow -type f -name '*.sh'                                                  -print0 2>/dev/null | xargs -0r chmod u+x           && \
find $PROJECT      -follow -name '.#*'                                                           -print0 2>/dev/null | xargs -0r rm                  &


# restart Apache if running
[ -f /var/run/httpd.pid ] && /bin/kill -HUP `cat /var/run/httpd.pid`

echo