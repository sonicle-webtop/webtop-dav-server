#!/bin/sh

INITIALDIR="`pwd`"
BASEDIR="`dirname $0`"
cd "$BASEDIR"
DIR="`pwd`"

if [ $# -lt 3 ]
then
	echo "Usage: $0 codegen-jar-file openapi-spec-file client-name"
	echo "  example: $0 swagger-codegen-cli.jar swagger20.json webtop-client-swagger20"
	exit 1
fi

CODEGEN_JAR="$1"
SPEC_FILE="$2"
CLIENT_NAME="$3"

if [ ! -f "$CODEGEN_JAR" ]
then
	echo "Error: CODEGEN_JAR is missing"
	exit 1
fi
if [ ! -f "$SPEC_FILE" ]
then
	echo "Error: SPEC_FILE is missing"
	exit 1
fi
if [ -z "$CLIENT_NAME" ]
then
	echo "Error: CLIENT_NAME is missing"
	exit 1
fi

MY_CODEGEN_DIR="../.swagger-codegen"
TEMPLATE_DIR="$MY_CODEGEN_DIR/templates/php"
CONFIG_FILE="$MY_CODEGEN_DIR/$CLIENT_NAME.json"
TEMP_DIR="$MY_CODEGEN_DIR/temp"
TARGET_DIR="../lib/$CLIENT_NAME"
SRC_DIR="$TEMP_DIR/SwaggerClient-php/lib"
SRC_FILES="$SRC_DIR/*"

if [ ! -f "$CONFIG_FILE" ]
then
	echo "Error: missing codegen configuration file [$CONFIG_FILE]"
	exit 1
fi

echo "Cleaning temp folder [$TEMP_DIR]"
rm -rf $TEMP_DIR
mkdir $TEMP_DIR
java -jar $CODEGEN_JAR generate -i $SPEC_FILE -l php -t $TEMPLATE_DIR -c $CONFIG_FILE -o $TEMP_DIR
echo "Overwriting target files [$TARGET_DIR]"
rm -rf $TARGET_DIR
mkdir $TARGET_DIR
cp -R $SRC_FILES $TARGET_DIR
rm -rf $TEMP_DIR
