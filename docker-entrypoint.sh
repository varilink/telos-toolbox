#!/usr/bin/env bash

set -e

if [ -v XDEBUG_CLIENT_HOST ]
then

  echo 'zend_extension=xdebug.so' >                                            \
    /etc/php/7.4/mods-available/xdebug.ini
  echo 'xdebug.mode=debug' >>                                                  \
    /etc/php/7.4/mods-available/xdebug.ini
  echo 'xdebug.start_with_request=yes' >>                                      \
    /etc/php/7.4/mods-available/xdebug.ini
  echo "xdebug.client_host=$XDEBUG_CLIENT_HOST" >>                             \
    /etc/php/7.4/mods-available/xdebug.ini

  if [ -v XDEBUG_CLIENT_PORT ]
  then

    echo "xdebug.client_port=$XDEBUG_CLIENT_PORT" >>                           \
      /etc/php/7.4/mods-available/xdebug.ini

  fi

fi

if [ $TOOL == 'get-peers' ] || [ $TOOL == 'api-report' ]
then

  exec php -f /usr/local/src/$TOOL.php -- "$@"

fi
