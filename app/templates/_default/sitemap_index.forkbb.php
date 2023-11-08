<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($p->sitemap as $loc => $lastmod)
<sitemap>
<loc>{{ $loc }}</loc>
    @if ($lastmod)
<lastmod>{{ \gmdate('c', $lastmod) }}</lastmod>
    @endif
</sitemap>
@endforeach
</sitemapindex>
