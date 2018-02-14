<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type='text/xsl' href='$StylesheetIndexPath'?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><% loop Sitemaps %>
	<sitemap>
		<loc>{$AbsoluteBaseURL}sitemap.xml/sitemap/$ClassName/$Page.xml</loc>
		<% if $LastModified %><lastmod>$LastModified</lastmod><% end_if %>
	</sitemap><% end_loop %>
</sitemapindex>
