#!/bin/sh

if test -z "$1"; then
	echo "Usage: $0 BIBNAME"
	exit 2
fi
BIBNAME=$1
WORKDIR=`pwd`/work

BIBFILE="$WORKDIR/$BIBNAME.bib"
XMLFILE="$WORKDIR/$BIBNAME.xml"
echo "Prefixing stuff ..."
#the parser sucks at {"} and screws up ...
sed s/{\"}/\\\&quote\;/ $BIBFILE |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/'{[^\]*{[^\]*".*}.*}.#'// | sed s/'#.{[^\]*{[^\]*".*}.*}'// |sed s/'\\&\/'/\&amp\;/ >$BIBFILE.tmp


echo "Real Parsing ..."
python bibtex2xml.py $BIBFILE.tmp > $XMLFILE.tmp

echo "Postfixing stuff ..."
# fix some strange &#2; and other stuff,
# like the bibtex: in every tag
sed -e s/\&\#2\;/?/ $XMLFILE.tmp |sed -e s/\&\#2\;/?/ |sed -e s/\&\#3\;/?/ |sed -e \
s/\&\#3\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ \
|sed -e s/\&\#4\;/?/ |sed -e s/\&\</?\</ | sed -e s/bibtex:// | \
sed -e s/bibtex:// | sed  s/\0x1B//g | sed  s/\0x0B//g | sed  s/\0x0C//g | sed  s/\0x02//g  > $XMLFILE

php ../SemanticMediaWiki/maintenance/SMW_refreshData.php --page="Database_$BIBNAME"
