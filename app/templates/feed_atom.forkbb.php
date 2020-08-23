<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title type="text">{!! $p->e($p->feed['title']) !!}</title>
  <link rel="self" type="application/atom+xml" href="{!! $p->feed['id'] !!}" />
  <link rel="alternate" type="text/html" href="{!! $p->feed['link'] !!}" />
  <updated>{!! $p->e(\gmdate('c', $p->feed['updated'])) !!}</updated>
  <generator>ForkBB</generator>
  <id>{!! $p->e($p->feed['id']) !!}</id>
@foreach($p->feed['items'] as $item)
  <entry>
    <title>{!! $p->e($item['title']) !!}</title>
    <link rel="alternate" type="text/html" href="{!! $item['link'] !!}" />
    <id>{!! $p->e($item['id']) !!}</id>
    <updated>{!! $p->e(\gmdate('c', $item['updated'])) !!}</updated>
    <published>{!! $p->e(\gmdate('c', $item['published'])) !!}</published>
		<author>
			<name>{!! $p->e($item['author']) !!}</name>
    @if ($item['isEmail'])
			<email>{!! $p->e($item['email']) !!}</email>
    @endif
		</author>
    <content type="html">{!! $p->e($item['content']) !!}</content>
  </entry>
@endforeach
</feed>
