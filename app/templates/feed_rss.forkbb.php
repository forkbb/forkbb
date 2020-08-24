<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <atom:link href="{!! $p->feed['id'] !!}" rel="self" type="application/rss+xml" />
    <title>{{ $p->feed['title'] }}</title>
    <link>{{ $p->feed['link'] }}</link>
    <description>{{ $p->feed['description'] }}</description>
    <pubDate>{{ \gmdate('r', $p->feed['updated']) }}</pubDate>
    <generator>ForkBB</generator>
@foreach($p->feed['items'] as $item)
    <item>
      <title>{{ $item['title'] }}</title>
      <link>{{ $item['link'] }}</link>
      <description>{{ $item['content'] }}</description>
      <author>{{ $item['email'] }} ({{ $item['author'] }})</author>
      <guid>{{ $item['id'] }}</guid>
      <pubDate>{{ \gmdate('r', $item['published']) }}</pubDate>
    </item>
@endforeach
  </channel>
</rss>
