<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title type="text">{{ $p->feed['title'] }}</title>
  <link rel="self" type="application/atom+xml" href="{{ $p->feed['id'] }}" />
  <link rel="alternate" type="text/html" href="{{ $p->feed['link'] }}" />
  <updated>{{ \gmdate('c', $p->feed['updated']) }}</updated>
  <generator>ForkBB</generator>
  <id>{{ $p->feed['id'] }}</id>
@foreach($p->feed['items'] as $item)
  <entry>
    <title>{{ $item['title'] }}</title>
    <link rel="alternate" type="text/html" href="{{ $item['link'] }}" />
    <id>{{ $item['id'] }}</id>
    <updated>{{ \gmdate('c', $item['updated']) }}</updated>
    <published>{{ \gmdate('c', $item['published']) }}</published>
    <author>
      <name>{{ $item['author'] }}</name>
    </author>
    <content type="html">{{ $item['content'] }}</content>
  </entry>
@endforeach
</feed>
