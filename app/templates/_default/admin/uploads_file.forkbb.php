<a id="id-{{ $key }}" class="f-link" href="{{ $cur['href'] }}" @if ($cur['rel']) rel="{{ $cur['rel'] }}" @endif title="{{ $cur['title'] or $cur['value'] }}">{{ $cur['value'] }}</a>
@if (\preg_match('%\.(webp|avif|jpe?g|gif|png|bmp)$%i', $cur['href']))
<span class="f-filelist-image-span"><a href="{{ $cur['href'] }}"><img class="f-filelist-image" src="{{ $cur['href'] }}" alt="{{ $cur['value'] }}" loading="lazy"></a></span>
@endif
