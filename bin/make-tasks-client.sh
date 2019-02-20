#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli.jar" "../../webtop-tasks/src/main/resources/com/sonicle/webtop/tasks/openapi-v1.json" "webtop-tasks-client"
