#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli.jar" "../../webtop-core/src/main/resources/com/sonicle/webtop/core/openapi-v1.json" "webtop-client-dav"
