# Common image used by all the tools in the toolbox
FROM debian:bullseye

RUN                                                                            \
  apt-get update                                                            && \
  apt-get --yes --no-install-recommends install                                \
    ca-certificates                                                            \
    php-cli                                                                    \
    php-curl                                                                   \
    php-xdebug

COPY docker-entrypoint.sh /

ENTRYPOINT [ "/docker-entrypoint.sh" ]
