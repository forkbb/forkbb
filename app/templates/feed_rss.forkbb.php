<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <atom:link href="{!! $p->feed['id'] !!}" rel="self" type="application/rss+xml" />
    <title>{!! $p->e($p->feed['title']) !!}</title>
    <link>{!! $p->e($p->feed['link']) !!}</link>
    <description>{!! $p->e($p->feed['description']) !!}</description>
    <pubDate>{!! $p->e(\gmdate('r', $p->feed['updated'])) !!}</pubDate>
    <generator>ForkBB</generator>
@foreach($p->feed['items'] as $item)
    <item>
      <title>{!! $p->e($item['title']) !!}</title>
      <link>{!! $p->e($item['link']) !!}</link>
      <description>{!! $p->e($item['content']) !!}</description>
      <author>{!! $p->e($item['email']) !!} ({!! $p->e($item['author']) !!})</author>
      <guid>{!! $p->e($item['id']) !!}</guid>
      <pubDate>{!! $p->e(\gmdate('r', $item['published'])) !!}</pubDate>
    </item>
@endforeach
  </channel>
</rss>
