#!/bin/sh

if test -z "$1"; then
	echo "Usage: $0 BIBNAME"
	exit 2
fi
BIBNAME=$1
PAGENAME=$2
WORKDIR=`pwd`/work

if test -z "$PAGENAME"; then
    PAGENAME="Database_$BIBNAME"
fi

BIBFILE="$WORKDIR/$BIBNAME.bib"
XMLFILE="$WORKDIR/$BIBNAME.xml"
echo "Prefixing stuff ..."
#the parser sucks at {"} and screws up ...
sed s/{\"}/\\\&quote\;/ $BIBFILE |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/{\"}/\\\&quote\;/ |sed s/'{[^\]*{[^\]*".*}.*}.#'// | sed s/'#.{[^\]*{[^\]*".*}.*}'// |sed s/'\\&\/'/\&amp\;/ >$BIBFILE.tmp

echo "Real Parsing ($BIBFILE)..."
python bibtex2xml.py $BIBFILE.tmp > $XMLFILE.tmp

echo "Postfixing stuff ..."
# fix some strange &#2; and other stuff,
# like the bibtex: in every tag
sed -e s/\&\#2\;/?/ $XMLFILE.tmp |sed -e s/\&\#2\;/?/ |sed -e s/\&\#3\;/?/ |sed -e \
s/\&\#3\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ \
|sed -e s/\&\#4\;/?/ |sed -e s/\&\</?\</ | sed -e s/bibtex:// | \
sed -e s/bibtex:// | sed  s/\0x1B//g | sed  s/\0x0B//g | sed  s/\0x0C//g | sed  s/\0x02//g  > $XMLFILE

echo "Updateing SMW page ($PAGENAME)..."
php ../SemanticMediaWiki/maintenance/SMW_refreshData.php --page="$PAGENAME"

echo "Finished."
