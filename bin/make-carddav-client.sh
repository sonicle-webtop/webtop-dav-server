#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli.jar" "../../webtop-contacts/src/main/resources/com/sonicle/webtop/contacts/openapi-v1.json" "webtop-client-carddav"
