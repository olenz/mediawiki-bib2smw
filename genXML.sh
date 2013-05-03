#use jabref to generate an xml:

WORKDIR=/web/icp/public_html/mediawiki/extensions/Bib2SMW/work
BIBFILE=$WORKDIR/icp.bib
XMLFILE=$WORKDIR/icp.xml

jabref --nogui --output=$XMLFILE.tmp,bibtexml  $BIBFILE

# fix some strange &#2; and other stuff,
# like the bibtex: in every tag
sed -e s/\&\#2\;/?/ $XMLFILE.tmp |sed -e s/\&\#2\;/?/ |sed -e s/\&\#3\;/?/ |sed -e \
s/\&\#3\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ |sed -e s/\&\#2\;/?/ \
|sed -e s/\&\#4\;/?/ |sed -e s/\&\</?\</ | sed -e s/bibtex:// | sed -e \
s/bibtex:// > $XMLFILE


