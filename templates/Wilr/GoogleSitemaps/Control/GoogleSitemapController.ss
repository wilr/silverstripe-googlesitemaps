<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type='text/xsl' href='{$AbsoluteLink('styleSheetIndex')}'?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><% loop Sitemaps %>
    <sitemap>
        <loc>{$Up.AbsoluteLink('sitemap')}/{$ClassName}/{$Page.xml}</loc>
        <% if $LastModified %><lastmod>{$LastModified}</lastmod><% end_if %>
    </sitemap><% end_loop %>
</sitemapindex>
