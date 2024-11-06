#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli-3.0.62.jar" "../../webtop-contacts/src/main/resources/com/sonicle/webtop/contacts/openapi-v2.json" "webtop-contacts-client"
