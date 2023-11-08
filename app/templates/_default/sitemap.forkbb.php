<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($p->sitemap as $loc => $lastmod)
<url>
<loc>{{ $loc }}</loc>
    @if ($lastmod)
<lastmod>{{ \gmdate('c', $lastmod) }}</lastmod>
    @endif
</url>
@endforeach
</urlset>
