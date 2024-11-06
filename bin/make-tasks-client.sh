#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

./generate-api-client.sh "swagger-codegen-cli-3.0.62.jar" "../../webtop-tasks/src/main/resources/com/sonicle/webtop/tasks/openapi-v2.json" "webtop-tasks-client"
