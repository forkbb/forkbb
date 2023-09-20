<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName>{{ $p->fTitle }}</ShortName>
  <Description>{{ $p->fTitle }}</Description>
  <InputEncoding>UTF-8</InputEncoding>
  <Image width="16" height="16" type="image/x-icon">{{ $p->imageLink }}</Image>
  <Url type="text/html" method="get" template="{{ $p->searchLink }}"/>
</OpenSearchDescription>
