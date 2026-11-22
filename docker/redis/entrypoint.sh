#!/bin/sh
# Password + ACL user: default user disabled, one ACL user from env. RESTORE, DEBUG, MODULE and CONFIG are denied.
set -eu
umask 077
printf 'user default off\nuser %s on >%s ~* &* +@all -restore -debug -module -config\n' "$REDIS_USER" "$REDIS_PASSWORD" > /tmp/users.acl
chown redis:redis /tmp/users.acl
exec docker-entrypoint.sh redis-server --aclfile /tmp/users.acl --appendonly yes
