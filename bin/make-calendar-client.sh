#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli.jar" "../../webtop-calendar/src/main/resources/com/sonicle/webtop/calendar/openapi-v1.json" "webtop-calendar-client"
